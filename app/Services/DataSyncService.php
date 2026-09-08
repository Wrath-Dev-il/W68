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
     * Read and send ONE chunk. The browser repeatedly calls this method until
     * done=true, keeping large Ledger/Sales tables away from one huge request.
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
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );

            if ($rowJson === false) {
                throw new RuntimeException("Unable to encode {$connection}.{$table} for transfer.");
            }

            $rowBytes = strlen($rowJson) + 1;

            // Keep normal requests small enough for a hosted PHP reverse proxy.
            // Always allow at least one row so a large image/blob can still be
            // transferred instead of creating an infinite retry loop.
            if (!empty($transportRows) && ($payloadBytes + $rowBytes) > $payloadLimit) {
                break;
            }

            $transportRows[] = $row;
            $payloadBytes += $rowBytes;
        }

        if (empty($transportRows)) {
            throw new RuntimeException("No rows could be prepared for {$connection}.{$table}.");
        }

        $receiver = $targetUrl . '/api/datasync/receive';

        $response = Http::asJson()
            ->acceptJson()
            ->withHeaders([
                'X-Datasync-Token' => $token,
                'X-Datasync-Source' => (string) config('app.url'),
            ])
            ->withOptions([
                'verify' => (bool) config('datasync.verify_ssl', true),
            ])
            ->connectTimeout((int) config('datasync.connect_timeout', 15))
            ->timeout((int) config('datasync.request_timeout', 180))
            ->retry(2, 1000, throw: false)
            ->post($receiver, [
                'connection' => $connection,
                'table' => $table,
                'rows' => $transportRows,
            ]);

        if (!$response->successful()) {
            $message = trim((string) ($response->json('message') ?: $response->body()));

            throw new RuntimeException(
                'HostForge rejected the sync chunk (HTTP ' . $response->status() . ')' .
                ($message !== '' ? ': ' . $message : '.')
            );
        }

        if ($response->json('success') !== true) {
            throw new RuntimeException(
                (string) ($response->json('message') ?: 'HostForge returned an unsuccessful sync response.')
            );
        }

        $processed = count($transportRows);
        $nextOffset = $offset + $processed;
        $done = $nextOffset >= $total || count($rawRows) < $limit;

        return [
            'connection' => $connection,
            'table' => $table,
            'processed' => $processed,
            'offset' => $offset,
            'next_offset' => $nextOffset,
            'total' => $total,
            'done' => $done,
            'payload_bytes' => $payloadBytes,
            'message' => $done
                ? "Finished syncing {$table}."
                : "Synced {$nextOffset} of {$total} rows.",
        ];
    }

    /**
     * HostForge receiver. Applies a chunk to the matching one of the six
     * production database connections using PRIMARY/UNIQUE-key upsert.
     */
    public function receiveChunk(
        string $providedToken,
        string $connection,
        string $table,
        array $rows
    ): array {
        if (!(bool) config('datasync.target_enabled', false)) {
            throw new RuntimeException('DataSync receiver is disabled on this environment.');
        }

        $expectedToken = trim((string) config('datasync.token', ''));

        if ($expectedToken === '') {
            throw new RuntimeException('DATASYNC_TOKEN is not configured on the receiver.');
        }

        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            throw new RuntimeException('Invalid DataSync token.');
        }

        $this->assertConnectionAllowed($connection);
        $this->assertTableAllowed($connection, $table);

        if (count($rows) > 1000) {
            throw new RuntimeException('DataSync chunk is too large. Maximum is 1000 rows.');
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
            throw new RuntimeException('No writable columns were received for this table.');
        }

        $presentColumns = array_keys($cleanRows[0]);
        $updateColumns = array_values(array_diff($presentColumns, $key));
        $db = DB::connection($connection);

        $db->beginTransaction();

        try {
            $db->statement('SET FOREIGN_KEY_CHECKS=0');

            if (empty($updateColumns)) {
                $db->table($table)->insertOrIgnore($cleanRows);
            } else {
                $db->table($table)->upsert($cleanRows, $key, $updateColumns);
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
                // Connection may already be gone; next request gets a new PDO.
            }
        }

        return [
            'connection' => $connection,
            'table' => $table,
            'received' => count($cleanRows),
            'sync_key' => $key,
        ];
    }

    private function chunkSize(): int
    {
        return max(10, min(1000, (int) config('datasync.chunk_size', 250)));
    }

    private function maxPayloadBytes(): int
    {
        return max(
            262144,
            min(8388608, (int) config('datasync.max_payload_bytes', 3145728))
        );
    }

    private function assertConnectionAllowed(string $connection): void
    {
        if (!array_key_exists($connection, (array) config('datasync.connections', []))) {
            throw new RuntimeException("DataSync connection '{$connection}' is not allowed.");
        }
    }

    private function assertTableAllowed(string $connection, string $table): void
    {
        if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new RuntimeException('Invalid table name.');
        }

        if ($this->isExcludedTable($table)) {
            throw new RuntimeException("Table {$table} is excluded from DataSync.");
        }

        if (!Schema::connection($connection)->hasTable($table)) {
            throw new RuntimeException("Table {$table} does not exist on connection {$connection}.");
        }
    }

    private function isExcludedTable(string $table): bool
    {
        $table = strtolower($table);

        foreach ((array) config('datasync.excluded_tables', []) as $excluded) {
            if ($table === strtolower((string) $excluded)) {
                return true;
            }
        }

        foreach ((array) config('datasync.excluded_prefixes', []) as $prefix) {
            $prefix = strtolower((string) $prefix);
            if ($prefix !== '' && str_starts_with($table, $prefix)) {
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

        return array_values(array_filter(array_map(
            fn ($row) => (string) ($row->TABLE_NAME ?? ''),
            $rows
        )));
    }

    /** Prefer PRIMARY KEY, otherwise use the first UNIQUE index. */
    private function syncKey(string $connection, string $table): array
    {
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
        return is_array($first) ? array_values($first) : [];
    }

    private function writableColumns(string $connection, string $table): array
    {
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

            if ($column === '' || str_contains($extra, 'GENERATED')) {
                continue;
            }

            $columns[] = $column;
        }

        return $columns;
    }

    private function binaryColumns(string $connection, string $table): array
    {
        $database = DB::connection($connection)->getDatabaseName();

        $rows = DB::connection($connection)->select(
            'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_SET_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?',
            [$database, $table]
        );

        $binaryTypes = [
            'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob',
            'geometry', 'point', 'linestring', 'polygon', 'multipoint',
            'multilinestring', 'multipolygon', 'geometrycollection',
        ];

        $binary = [];

        foreach ($rows as $row) {
            $column = (string) ($row->COLUMN_NAME ?? '');
            $type = strtolower((string) ($row->DATA_TYPE ?? ''));
            $charset = $row->CHARACTER_SET_NAME ?? null;

            if ($column !== '' && (in_array($type, $binaryTypes, true) || $charset === null && str_contains($type, 'blob'))) {
                $binary[$column] = true;
            }
        }

        return $binary;
    }

    private function encodeRow(array $row, array $binaryColumns): array
    {
        foreach ($row as $column => $value) {
            if (!is_string($value)) {
                continue;
            }

            $mustEncode = isset($binaryColumns[$column]);

            if (!$mustEncode && function_exists('mb_check_encoding')) {
                $mustEncode = !mb_check_encoding($value, 'UTF-8');
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
            && array_key_exists(self::BINARY_MARKER, $value)
        ) {
            $decoded = base64_decode((string) $value[self::BINARY_MARKER], true);

            if ($decoded === false) {
                throw new RuntimeException('Invalid binary value received by DataSync.');
            }

            return $decoded;
        }

        return $value;
    }
}
