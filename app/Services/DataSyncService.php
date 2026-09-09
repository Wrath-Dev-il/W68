<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class DataSyncService
{
    private const BINARY_MARKER = '__w68_datasync_binary_b64';

    public function configuration(): array
    {
        $dashboards = [];

        foreach ((array) config('datasync.connections', []) as $connection => $meta) {
            $tables = [];
            $error = null;

            try {
                $tables = collect($this->listTables($connection))
                    ->reject(fn (string $table) => $this->isExcludedTable($table))
                    ->map(function (string $table) use ($connection) {
                        $key = $this->syncKey($connection, $table);

                        return [
                            'connection' => $connection,
                            'table' => $table,
                            'label' => ucwords(str_replace('_', ' ', $table)),
                            'syncable' => !empty($key),
                            'sync_key' => $key,
                        ];
                    })
                    ->values()
                    ->all();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }

            $dashboards[] = [
                'key' => $connection,
                'label' => (string) ($meta['label'] ?? ucfirst($connection)),
                'icon' => (string) ($meta['icon'] ?? 'database'),
                'tables' => $tables,
                'error' => $error,
            ];
        }

        return [
            'dashboards' => $dashboards,
            'source_enabled' => (bool) config('datasync.source_enabled', false),
            'target_enabled' => (bool) config('datasync.target_enabled', false),
            'target_url' => rtrim((string) config('datasync.target_url', ''), '/'),
            'chunk_size' => $this->chunkSize(),
            'max_payload_bytes' => $this->maxPayloadBytes(),
            'auto_interval_ms' => max(
                300000,
                (int) config('datasync.auto_interval_seconds', 1800) * 1000
            ),
        ];
    }

    /**
     * Sends one source chunk. The browser repeats this until done=true.
     *
     * V4 fixes:
     * - caps normal payloads below the HostForge/nginx request limit;
     * - automatically halves a chunk and retries when nginx returns HTTP 413;
     * - never marks a partially-sent final chunk as complete.
     */
    public function pushChunk(
        string $connection,
        string $table,
        int $offset = 0,
        ?int $knownTotal = null,
        ?int $requestedLimit = null
    ): array {
        if (!(bool) config('datasync.source_enabled', false)) {
            throw new RuntimeException('DataSync source is disabled on this environment.');
        }

        $this->assertConnectionAllowed($connection);
        $this->assertTableAllowed($connection, $table);

        $targetUrl = rtrim((string) config('datasync.target_url', ''), '/');
        $token = trim((string) config('datasync.token', ''));

        if ($targetUrl === '') {
            throw new RuntimeException('DATASYNC_TARGET_URL is not configured.');
        }

        if ($token === '') {
            throw new RuntimeException('DATASYNC_TOKEN is not configured.');
        }

        $key = $this->syncKey($connection, $table);

        if (empty($key)) {
            throw new RuntimeException(
                "Table {$table} has no PRIMARY/UNIQUE key and cannot be safely synchronized."
            );
        }

        $limit = $requestedLimit ?? $this->chunkSize();
        $limit = max(10, min(1000, $limit));
        $offset = max(0, $offset);

        $total = $knownTotal;

        if ($total === null) {
            $total = (int) DB::connection($connection)->table($table)->count();
        }

        $query = DB::connection($connection)->table($table);

        foreach ($key as $column) {
            $query->orderBy($column);
        }

        $rawRows = $query
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();

        if (empty($rawRows)) {
            return [
                'connection' => $connection,
                'table' => $table,
                'processed' => 0,
                'offset' => $offset,
                'next_offset' => $offset,
                'total' => $total,
                'done' => true,
                'message' => $total === 0 ? 'Table is empty.' : 'No more rows to sync.',
            ];
        }

        $binaryColumns = $this->binaryColumns($connection, $table);
        $transportRows = [];
        $payloadBytes = 0;
        $payloadLimit = $this->maxPayloadBytes();

        foreach ($rawRows as $rawRow) {
            $row = $this->encodeRow($rawRow, $binaryColumns);

            $rowJson = json_encode(
                $row,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            if ($rowJson === false) {
                throw new RuntimeException(
                    "Unable to encode {$connection}.{$table} for transfer."
                );
            }

            $rowBytes = strlen($rowJson) + 1;

            if (!empty($transportRows) && ($payloadBytes + $rowBytes) > $payloadLimit) {
                break;
            }

            $transportRows[] = $row;
            $payloadBytes += $rowBytes;
        }

        if (empty($transportRows)) {
            throw new RuntimeException(
                "No rows could be prepared for {$connection}.{$table}."
            );
        }

        $receiver = $targetUrl . '/api/datasync/receive';
        $originalPreparedCount = count($transportRows);
        $reducedForProxy = false;

        while (true) {
            $response = $this->postReceiver(
                $receiver,
                $token,
                $connection,
                $table,
                $transportRows
            );

            if ($response->status() !== 413) {
                break;
            }

            if (count($transportRows) <= 1) {
                throw new RuntimeException(
                    "HostForge rejected a single {$connection}.{$table} row with HTTP 413. "
                    . 'That individual row is larger than the reverse-proxy request limit.'
                );
            }

            $transportRows = array_slice(
                $transportRows,
                0,
                max(1, intdiv(count($transportRows), 2))
            );

            $payloadBytes = $this->payloadBytes(
                $connection,
                $table,
                $transportRows
            );

            $reducedForProxy = true;
        }

        if (!$response->successful()) {
            $message = trim(
                (string) ($response->json('message') ?: $response->body())
            );

            throw new RuntimeException(
                'HostForge rejected the sync chunk (HTTP '
                . $response->status()
                . ')'
                . ($message !== '' ? ': ' . $message : '.')
            );
        }

        if ($response->json('success') !== true) {
            throw new RuntimeException(
                (string) (
                    $response->json('message')
                    ?: 'HostForge returned an unsuccessful sync response.'
                )
            );
        }

        $processed = count($transportRows);
        $nextOffset = $offset + $processed;

        // IMPORTANT: use total only. A byte-limit or HTTP-413 retry may send
        // fewer rows than were originally fetched, including on the final page.
        $done = $nextOffset >= $total;

        return [
            'connection' => $connection,
            'table' => $table,
            'processed' => $processed,
            'offset' => $offset,
            'next_offset' => $nextOffset,
            'total' => $total,
            'done' => $done,
            'payload_bytes' => $payloadBytes,
            'proxy_reduced' => $reducedForProxy,
            'prepared_rows' => $originalPreparedCount,
            'sent_rows' => $processed,
            'message' => $done
                ? "Finished syncing {$table}."
                : "Synced {$nextOffset} of {$total} rows.",
        ];
    }

    /**
     * HostForge receiver.
     *
     * Legacy XAMPP data contains zero DATE/DATETIME values. HostForge's
     * MariaDB uses a stricter SQL mode, so this receiver temporarily removes
     * only the strict/zero-date modes for this connection while the upsert is
     * performed, then restores the original session SQL mode immediately.
     */
    public function receiveChunk(
        string $providedToken,
        string $connection,
        string $table,
        array $rows
    ): array {
        if (!(bool) config('datasync.target_enabled', false)) {
            throw new RuntimeException(
                'DataSync receiver is disabled on this environment.'
            );
        }

        $expectedToken = trim((string) config('datasync.token', ''));

        if ($expectedToken === '') {
            throw new RuntimeException(
                'DATASYNC_TOKEN is not configured on the receiver.'
            );
        }

        if (
            $providedToken === ''
            || !hash_equals($expectedToken, $providedToken)
        ) {
            throw new RuntimeException('Invalid DataSync token.');
        }

        $this->assertConnectionAllowed($connection);
        $this->assertTableAllowed($connection, $table);

        if (count($rows) > 1000) {
            throw new RuntimeException(
                'DataSync chunk is too large. Maximum is 1000 rows.'
            );
        }

        if (empty($rows)) {
            return [
                'connection' => $connection,
                'table' => $table,
                'received' => 0,
            ];
        }

        $key = $this->syncKey($connection, $table);

        if (empty($key)) {
            throw new RuntimeException(
                "Table {$table} has no PRIMARY/UNIQUE key and cannot be safely synchronized."
            );
        }

        $writableColumns = $this->writableColumns($connection, $table);
        $allowedColumns = array_fill_keys($writableColumns, true);
        $cleanRows = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $clean = [];

            foreach ($row as $column => $value) {
                if (!isset($allowedColumns[$column])) {
                    continue;
                }

                $clean[$column] = $this->decodeValue($value);
            }

            if (!empty($clean)) {
                $cleanRows[] = $clean;
            }
        }

        if (empty($cleanRows)) {
            throw new RuntimeException(
                'No writable columns were received for this table.'
            );
        }

        $presentColumns = array_keys($cleanRows[0]);
        $updateColumns = array_values(array_diff($presentColumns, $key));
        $db = DB::connection($connection);

        $originalSqlMode = $this->sessionSqlMode($db);
        $legacySqlMode = $this->legacyCompatibleSqlMode($originalSqlMode);

        $this->setSessionSqlMode($db, $legacySqlMode);

        $removedUniqueConflicts = 0;

        $db->beginTransaction();

        try {
            $db->statement('SET FOREIGN_KEY_CHECKS=0');

            $removedUniqueConflicts = $this->removeSecondaryUniqueConflicts(
                $db,
                $connection,
                $table,
                $cleanRows,
                $key
            );

            if (empty($updateColumns)) {
                $db->table($table)->insertOrIgnore($cleanRows);
            } else {
                $db->table($table)->upsert(
                    $cleanRows,
                    $key,
                    $updateColumns
                );
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->transactionLevel() > 0) {
                $db->rollBack();
            }

            throw $e;
        } finally {
            try {
                $db->statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable $ignored) {
                // A new request will receive a fresh connection if this one died.
            }

            try {
                $this->setSessionSqlMode($db, $originalSqlMode);
            } catch (Throwable $ignored) {
                // Same as above: do not hide the original import result.
            }
        }

        return [
            'connection' => $connection,
            'table' => $table,
            'received' => count($cleanRows),
            'sync_key' => $key,
            'removed_unique_conflicts' => $removedUniqueConflicts,
        ];
    }

    private function postReceiver(
        string $receiver,
        string $token,
        string $connection,
        string $table,
        array $rows
    ) {
        return Http::asJson()
            ->acceptJson()
            ->withHeaders([
                'X-Datasync-Token' => $token,
                'X-Datasync-Source' => (string) config('app.url'),
            ])
            ->withOptions([
                'verify' => (bool) config('datasync.verify_ssl', true),
            ])
            ->connectTimeout(
                (int) config('datasync.connect_timeout', 15)
            )
            ->timeout(
                (int) config('datasync.request_timeout', 180)
            )
            ->post($receiver, [
                'connection' => $connection,
                'table' => $table,
                'rows' => $rows,
            ]);
    }

    private function payloadBytes(
        string $connection,
        string $table,
        array $rows
    ): int {
        $json = json_encode(
            [
                'connection' => $connection,
                'table' => $table,
                'rows' => $rows,
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $json === false ? 0 : strlen($json);
    }

    private function sessionSqlMode($db): string
    {
        $row = $db->selectOne(
            'SELECT @@SESSION.sql_mode AS sql_mode'
        );

        return (string) ($row->sql_mode ?? '');
    }

    private function legacyCompatibleSqlMode(string $sqlMode): string
    {
        $remove = [
            'STRICT_TRANS_TABLES' => true,
            'STRICT_ALL_TABLES' => true,
            'NO_ZERO_DATE' => true,
            'NO_ZERO_IN_DATE' => true,
        ];

        $modes = [];

        foreach (explode(',', $sqlMode) as $mode) {
            $mode = trim($mode);

            if ($mode === '') {
                continue;
            }

            if (isset($remove[strtoupper($mode)])) {
                continue;
            }

            $modes[] = $mode;
        }

        return implode(',', $modes);
    }

    private function setSessionSqlMode($db, string $sqlMode): void
    {
        $quoted = $db->getPdo()->quote($sqlMode);
        $db->unprepared("SET SESSION sql_mode = {$quoted}");
    }

    private function chunkSize(): int
    {
        return max(
            10,
            min(1000, (int) config('datasync.chunk_size', 250))
        );
    }

    private function maxPayloadBytes(): int
    {
        return max(
            131072,
            min(
                8388608,
                (int) config(
                    'datasync.max_payload_bytes',
                    524288
                )
            )
        );
    }

    private function assertConnectionAllowed(string $connection): void
    {
        if (
            !array_key_exists(
                $connection,
                (array) config('datasync.connections', [])
            )
        ) {
            throw new RuntimeException(
                "DataSync connection '{$connection}' is not allowed."
            );
        }
    }

    private function assertTableAllowed(
        string $connection,
        string $table
    ): void {
        if (
            $table === ''
            || !preg_match('/^[A-Za-z0-9_]+$/', $table)
        ) {
            throw new RuntimeException('Invalid table name.');
        }

        if ($this->isExcludedTable($table)) {
            throw new RuntimeException(
                "Table {$table} is excluded from DataSync."
            );
        }

        if (!Schema::connection($connection)->hasTable($table)) {
            throw new RuntimeException(
                "Table {$table} does not exist on connection {$connection}."
            );
        }
    }

    private function isExcludedTable(string $table): bool
    {
        $table = strtolower($table);

        foreach (
            (array) config('datasync.excluded_tables', [])
            as $excluded
        ) {
            if ($table === strtolower((string) $excluded)) {
                return true;
            }
        }

        foreach (
            (array) config('datasync.excluded_prefixes', [])
            as $prefix
        ) {
            $prefix = strtolower((string) $prefix);

            if (
                $prefix !== ''
                && str_starts_with($table, $prefix)
            ) {
                return true;
            }
        }

        return false;
    }

    private function listTables(string $connection): array
    {
        $this->assertConnectionAllowed($connection);
        $database = DB::connection($connection)->getDatabaseName();

        $rows = DB::connection($connection)->select(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
               AND TABLE_TYPE = ?
             ORDER BY TABLE_NAME',
            [$database, 'BASE TABLE']
        );

        return array_values(
            array_filter(
                array_map(
                    fn ($row) => (string) (
                        $row->TABLE_NAME ?? ''
                    ),
                    $rows
                )
            )
        );
    }

    /**
     * Prefer PRIMARY KEY, otherwise use the first UNIQUE index.
     */
    private function syncKey(
        string $connection,
        string $table
    ): array {
        $database = DB::connection($connection)->getDatabaseName();

        $rows = DB::connection($connection)->select(
            'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND NON_UNIQUE = 0
             ORDER BY
                CASE WHEN INDEX_NAME = ? THEN 0 ELSE 1 END,
                INDEX_NAME,
                SEQ_IN_INDEX',
            [$database, $table, 'PRIMARY']
        );

        $indexes = [];

        foreach ($rows as $row) {
            $index = (string) ($row->INDEX_NAME ?? '');
            $column = (string) ($row->COLUMN_NAME ?? '');

            if ($index !== '' && $column !== '') {
                $indexes[$index][] = $column;
            }
        }

        if (!empty($indexes['PRIMARY'])) {
            return array_values($indexes['PRIMARY']);
        }

        $first = reset($indexes);

        return is_array($first)
            ? array_values($first)
            : [];
    }

    /**
     * W68_DATASYNC_SECONDARY_UNIQUE_CONFLICT_FIX_20260909
     *
     * The source and HostForge target can temporarily disagree on a row's
     * PRIMARY KEY while still sharing another UNIQUE value (for example
     * masterlist.products.product_code). A normal multi-row upsert can then
     * fail with SQLSTATE 1062 when it updates/inserts the source primary key
     * into a UNIQUE value already owned by a stale target row.
     *
     * Source is authoritative. Before upsert, remove only target rows that:
     * - collide with an incoming row on a SECONDARY UNIQUE index; and
     * - do NOT have the same DataSync key as that incoming source row.
     *
     * This lets the following upsert recreate the exact source identity.
     */
    private function removeSecondaryUniqueConflicts(
        $db,
        string $connection,
        string $table,
        array $rows,
        array $syncKey
    ): int {
        $indexes = $this->uniqueIndexes($connection, $table);

        if (count($indexes) <= 1) {
            return 0;
        }

        $removed = 0;

        foreach ($indexes as $indexName => $columns) {
            $columns = array_values($columns);

            // Never remove a row merely because it matches the authoritative
            // PRIMARY/selected DataSync key itself.
            if ($columns === array_values($syncKey)) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                // MySQL UNIQUE indexes permit multiple NULL values. A row with
                // a missing/NULL indexed value cannot create this collision.
                $completeUniqueValue = true;
                foreach ($columns as $column) {
                    if (
                        !array_key_exists($column, $row)
                        || $row[$column] === null
                    ) {
                        $completeUniqueValue = false;
                        break;
                    }
                }

                if (!$completeUniqueValue) {
                    continue;
                }

                // We also need every sync-key value so we can distinguish the
                // correct target row from a stale row that owns the same
                // secondary UNIQUE value.
                $completeSyncKey = true;
                foreach ($syncKey as $keyColumn) {
                    if (
                        !array_key_exists($keyColumn, $row)
                        || $row[$keyColumn] === null
                    ) {
                        $completeSyncKey = false;
                        break;
                    }
                }

                if (!$completeSyncKey) {
                    continue;
                }

                $selectColumns = array_values(
                    array_unique(array_merge($syncKey, $columns))
                );

                $conflictQuery = $db->table($table);

                foreach ($columns as $column) {
                    $conflictQuery->where($column, '=', $row[$column]);
                }

                $conflicts = $conflictQuery
                    ->lockForUpdate()
                    ->get($selectColumns);

                foreach ($conflicts as $existing) {
                    $sameSyncKey = true;

                    foreach ($syncKey as $keyColumn) {
                        $incomingValue = $row[$keyColumn] ?? null;
                        $existingValue = $existing->{$keyColumn} ?? null;

                        if ((string) $incomingValue !== (string) $existingValue) {
                            $sameSyncKey = false;
                            break;
                        }
                    }

                    if ($sameSyncKey) {
                        continue;
                    }

                    $deleteQuery = $db->table($table);

                    foreach ($syncKey as $keyColumn) {
                        $existingValue = $existing->{$keyColumn} ?? null;

                        if ($existingValue === null) {
                            $deleteQuery->whereNull($keyColumn);
                        } else {
                            $deleteQuery->where($keyColumn, '=', $existingValue);
                        }
                    }

                    $removed += (int) $deleteQuery->delete();
                }
            }
        }

        return $removed;
    }

    /**
     * Return every non-prefix UNIQUE index in declared column order.
     *
     * Prefix UNIQUE indexes are intentionally skipped because equality on the
     * full column value is not equivalent to equality on an indexed prefix.
     */
    private function uniqueIndexes(
        string $connection,
        string $table
    ): array {
        $database = DB::connection($connection)->getDatabaseName();

        $rows = DB::connection($connection)->select(
            'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, SUB_PART
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND NON_UNIQUE = 0
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$database, $table]
        );

        $indexes = [];
        $skip = [];

        foreach ($rows as $row) {
            $index = (string) ($row->INDEX_NAME ?? '');
            $column = (string) ($row->COLUMN_NAME ?? '');
            $subPart = $row->SUB_PART ?? null;

            if ($index === '' || $column === '') {
                continue;
            }

            if ($subPart !== null) {
                $skip[$index] = true;
                continue;
            }

            $indexes[$index][] = $column;
        }

        foreach (array_keys($skip) as $index) {
            unset($indexes[$index]);
        }

        return $indexes;
    }
    private function writableColumns(
        string $connection,
        string $table
    ): array {
        $database = DB::connection($connection)->getDatabaseName();

        $rows = DB::connection($connection)->select(
            'SELECT COLUMN_NAME, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$database, $table]
        );

        $columns = [];

        foreach ($rows as $row) {
            $column = (string) ($row->COLUMN_NAME ?? '');
            $extra = strtoupper((string) ($row->EXTRA ?? ''));

            if (
                $column === ''
                || str_contains($extra, 'GENERATED')
            ) {
                continue;
            }

            $columns[] = $column;
        }

        return $columns;
    }

    private function binaryColumns(
        string $connection,
        string $table
    ): array {
        $database = DB::connection($connection)->getDatabaseName();

        $rows = DB::connection($connection)->select(
            'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_SET_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?',
            [$database, $table]
        );

        $binaryTypes = [
            'binary',
            'varbinary',
            'tinyblob',
            'blob',
            'mediumblob',
            'longblob',
            'geometry',
            'point',
            'linestring',
            'polygon',
            'multipoint',
            'multilinestring',
            'multipolygon',
            'geometrycollection',
        ];

        $binary = [];

        foreach ($rows as $row) {
            $column = (string) ($row->COLUMN_NAME ?? '');
            $type = strtolower((string) ($row->DATA_TYPE ?? ''));
            $charset = $row->CHARACTER_SET_NAME ?? null;

            if (
                $column !== ''
                && (
                    in_array($type, $binaryTypes, true)
                    || (
                        $charset === null
                        && str_contains($type, 'blob')
                    )
                )
            ) {
                $binary[$column] = true;
            }
        }

        return $binary;
    }

    private function encodeRow(
        array $row,
        array $binaryColumns
    ): array {
        foreach ($row as $column => $value) {
            if (!is_string($value)) {
                continue;
            }

            $mustEncode = isset($binaryColumns[$column]);

            if (
                !$mustEncode
                && function_exists('mb_check_encoding')
            ) {
                $mustEncode = !mb_check_encoding(
                    $value,
                    'UTF-8'
                );
            }

            if ($mustEncode) {
                $row[$column] = [
                    self::BINARY_MARKER => base64_encode($value),
                ];
            }
        }

        return $row;
    }

    private function decodeValue(mixed $value): mixed
    {
        if (
            is_array($value)
            && count($value) === 1
            && array_key_exists(
                self::BINARY_MARKER,
                $value
            )
        ) {
            $decoded = base64_decode(
                (string) $value[self::BINARY_MARKER],
                true
            );

            if ($decoded === false) {
                throw new RuntimeException(
                    'Invalid binary value received by DataSync.'
                );
            }

            return $decoded;
        }

        return $value;
    }
}
