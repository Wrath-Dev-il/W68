<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DeveloperDatabaseBackupService
{
    public static function connectionMap(): array
    {
        return [
            'masterlist' => 'masterlist',
            'purchase' => 'purchase',
            'sales' => 'sales',
            'accounting' => 'accounting',
            'ledger' => 'ledger',
        ];
    }

    public function exportSqlGz(string $connection, string $group): string
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $tmp = rtrim(sys_get_temp_dir(), '\\/');
        $path = $tmp . DIRECTORY_SEPARATOR
            . 'w68_' . preg_replace('/[^a-z0-9_-]+/i', '_', $group)
            . '_' . bin2hex(random_bytes(8)) . '.sql.gz';

        try {
            if (!$this->exportWithNativeDump($connection, $path)) {
                $this->exportWithPhp($connection, $path);
            }

            if (!is_file($path) || filesize($path) <= 0) {
                throw new RuntimeException('Database backup file was not created.');
            }

            return $path;
        } catch (Throwable $e) {
            @unlink($path);
            throw $e;
        }
    }

    private function exportWithNativeDump(string $connection, string $path): bool
    {
        $binary = $this->dumpBinary();

        if ($binary === null || !function_exists('proc_open')) {
            return false;
        }

        $cfg = (array) config("database.connections.{$connection}", []);
        $database = trim((string) ($cfg['database'] ?? ''));
        $username = (string) ($cfg['username'] ?? '');
        $password = (string) ($cfg['password'] ?? '');
        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 3306);
        $socket = trim((string) ($cfg['unix_socket'] ?? ''));

        if ($database === '' || $username === '') {
            throw new RuntimeException("Database connection {$connection} is missing database credentials.");
        }

        $tmp = rtrim(sys_get_temp_dir(), '\\/');
        $cnfPath = $tmp . DIRECTORY_SEPARATOR . 'w68_dump_' . bin2hex(random_bytes(8)) . '.cnf';
        $errPath = $tmp . DIRECTORY_SEPARATOR . 'w68_dump_' . bin2hex(random_bytes(8)) . '.err';

        $lines = [
            '[client]',
            'user=' . $this->clientOption($username),
            'password=' . $this->clientOption($password),
        ];

        if ($socket !== '') {
            $lines[] = 'socket=' . $this->clientOption($socket);
        } else {
            $lines[] = 'host=' . $this->clientOption($host);
            $lines[] = 'port=' . $port;
        }

        file_put_contents($cnfPath, implode(PHP_EOL, $lines) . PHP_EOL);
        @chmod($cnfPath, 0600);

        $command = [
            $binary,
            '--defaults-extra-file=' . $cnfPath,
            '--single-transaction',
            '--quick',
            '--routines',
            '--events',
            '--triggers',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--add-drop-table',
            '--databases',
            $database,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $errPath, 'a'],
        ];

        $process = null;
        $gzip = null;

        try {
            $process = proc_open($command, $descriptors, $pipes);
            if (!is_resource($process)) {
                return false;
            }

            fclose($pipes[0]);

            $gzip = gzopen($path, 'wb9');
            if ($gzip === false) {
                throw new RuntimeException('Unable to create gzip database backup.');
            }

            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Unable to read database dump output.');
                }

                if ($chunk !== '' && gzwrite($gzip, $chunk) === false) {
                    throw new RuntimeException('Unable to write gzip database backup.');
                }
            }

            fclose($pipes[1]);
            gzclose($gzip);
            $gzip = null;

            $exitCode = proc_close($process);
            $process = null;

            if ($exitCode !== 0) {
                $error = trim((string) @file_get_contents($errPath));
                @unlink($path);

                throw new RuntimeException(
                    'Database dump failed'
                    . ($error !== '' ? ': ' . mb_substr($error, 0, 1000) : '.')
                );
            }

            return true;
        } finally {
            if (is_resource($gzip)) {
                @gzclose($gzip);
            }

            if (is_resource($process)) {
                @proc_terminate($process);
                @proc_close($process);
            }

            @unlink($cnfPath);
            @unlink($errPath);
        }
    }

    private function exportWithPhp(string $connection, string $path): void
    {
        $db = DB::connection($connection);
        $database = (string) config("database.connections.{$connection}.database", $connection);
        $gzip = gzopen($path, 'wb9');

        if ($gzip === false) {
            throw new RuntimeException('Unable to create gzip database backup.');
        }

        $write = static function (string $text) use ($gzip): void {
            if (gzwrite($gzip, $text) === false) {
                throw new RuntimeException('Unable to write database backup.');
            }
        };

        try {
            $write("-- W68 MariaDB/MySQL SQL backup\n");
            $write("-- Database: " . $database . "\n");
            $write("-- Generated: " . now('Asia/Manila')->format('Y-m-d H:i:s T') . "\n\n");
            $write("/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
            $write("/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
            $write("/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
            $write("/*!40101 SET NAMES utf8mb4 */;\n");
            $write("/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
            $write("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
            $write("/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n\n");

            $objects = $db->select('SHOW FULL TABLES');
            $tables = [];
            $views = [];

            foreach ($objects as $object) {
                $values = array_values((array) $object);
                $name = (string) ($values[0] ?? '');
                $type = strtoupper((string) ($values[1] ?? 'BASE TABLE'));

                if ($name === '') {
                    continue;
                }

                if ($type === 'VIEW') {
                    $views[] = $name;
                } else {
                    $tables[] = $name;
                }
            }

            foreach ($tables as $table) {
                $identifier = $this->quoteIdentifier($table);
                $createRows = $db->select('SHOW CREATE TABLE ' . $identifier);
                if (!$createRows) {
                    continue;
                }

                $createValues = array_values((array) $createRows[0]);
                $createSql = (string) ($createValues[1] ?? '');

                $write("--\n-- Table structure for table " . $table . "\n--\n\n");
                $write('DROP TABLE IF EXISTS ' . $identifier . ";\n");
                $write($createSql . ";\n\n");

                $columns = $db->select('SHOW COLUMNS FROM ' . $identifier);
                $columnNames = array_map(
                    static fn ($column) => (string) $column->Field,
                    $columns
                );

                if (empty($columnNames)) {
                    continue;
                }

                $binaryColumns = [];
                foreach ($columns as $column) {
                    $type = strtolower((string) ($column->Type ?? ''));
                    $binaryColumns[(string) $column->Field] =
                        str_contains($type, 'blob')
                        || str_contains($type, 'binary')
                        || preg_match('/^bit(?:\\(|$)/', $type) === 1;
                }

                $quotedColumns = implode(', ', array_map(
                    fn ($column) => $this->quoteIdentifier($column),
                    $columnNames
                ));

                $primaryKey = null;
                try {
                    $keys = $db->select(
                        'SHOW KEYS FROM ' . $identifier . " WHERE Key_name = 'PRIMARY'"
                    );
                    $primaryKey = $keys[0]->Column_name ?? null;
                } catch (Throwable $ignored) {
                }

                $flush = function ($chunk) use (
                    $write,
                    $identifier,
                    $quotedColumns,
                    $columnNames,
                    $binaryColumns
                ): void {
                    if ($chunk->isEmpty()) {
                        return;
                    }

                    $rowsSql = [];

                    foreach ($chunk as $row) {
                        $values = [];

                        foreach ($columnNames as $column) {
                            $values[] = $this->sqlValue(
                                $row->{$column} ?? null,
                                (bool) ($binaryColumns[$column] ?? false)
                            );
                        }

                        $rowsSql[] = '(' . implode(',', $values) . ')';
                    }

                    $write(
                        'INSERT INTO ' . $identifier
                        . ' (' . $quotedColumns . ") VALUES\n"
                        . implode(",\n", $rowsSql)
                        . ";\n"
                    );
                };

                $write("--\n-- Data for table " . $table . "\n--\n");

                $query = $db->table($table);

                if ($primaryKey) {
                    $query->orderBy($primaryKey)->chunkById(
                        250,
                        function ($chunk) use ($flush): void {
                            $flush($chunk);
                        },
                        $primaryKey
                    );
                } else {
                    $buffer = collect();

                    foreach ($query->cursor() as $row) {
                        $buffer->push($row);

                        if ($buffer->count() >= 250) {
                            $flush($buffer);
                            $buffer = collect();
                        }
                    }

                    if ($buffer->isNotEmpty()) {
                        $flush($buffer);
                    }
                }

                $write("\n");
            }

            foreach ($views as $view) {
                $identifier = $this->quoteIdentifier($view);
                $createRows = $db->select('SHOW CREATE VIEW ' . $identifier);

                if (!$createRows) {
                    continue;
                }

                $createValues = array_values((array) $createRows[0]);
                $createSql = (string) ($createValues[1] ?? '');

                if ($createSql === '') {
                    continue;
                }

                $write("--\n-- View structure for view " . $view . "\n--\n\n");
                $write('DROP VIEW IF EXISTS ' . $identifier . ";\n");
                $write($createSql . ";\n\n");
            }

            $write("/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n");
            $write("/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
            $write("/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");
            $write("/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
            $write("/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
            $write("/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
        } finally {
            gzclose($gzip);
        }
    }

    private function dumpBinary(): ?string
    {
        foreach ([
            '/usr/bin/mariadb-dump',
            '/usr/local/bin/mariadb-dump',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ] as $binary) {
            if (is_file($binary) && is_executable($binary)) {
                return $binary;
            }
        }

        return null;
    }

    private function clientOption(string $value): string
    {
        return '"' . addcslashes($value, "\\\"\n\r") . '"';
    }

    private function quoteIdentifier(string $value): string
    {
        $tick = chr(96);
        return $tick . str_replace($tick, $tick . $tick, $value) . $tick;
    }

    private function sqlValue($value, bool $binaryColumn): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        $value = (string) $value;

        if ($binaryColumn) {
            return $value === '' ? "''" : '0x' . bin2hex($value);
        }

        return "'" . str_replace(
            ["\\", "\0", "\n", "\r", "\x1a", "'"],
            ["\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'"],
            $value
        ) . "'";
    }
}
