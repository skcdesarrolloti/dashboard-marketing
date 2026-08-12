<?php

declare(strict_types=1);

namespace App;

use mysqli;
use Throwable;

final class Database
{
    private static ?mysqli $connection = null;
    private static array $tableExistsCache = [];
    private static array $columnsCache = [];

    public static function connection(): mysqli
    {
        if (self::$connection instanceof mysqli) {
            return self::$connection;
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $dbName = (string) app_config('db.database', '');
        if ($dbName === '') {
            http_response_code(500);
            exit('Configura SKC_DB_NAME en el archivo .env externo antes de usar el dashboard.');
        }

        $connection = @new mysqli(
            (string) app_config('db.host', 'localhost'),
            (string) app_config('db.username', 'root'),
            (string) app_config('db.password', ''),
            $dbName
        );

        if ($connection->connect_errno) {
            http_response_code(500);
            exit('No fue posible conectar con la base de datos.');
        }

        $connection->set_charset((string) app_config('db.charset', 'utf8mb4'));
        self::$connection = $connection;

        return $connection;
    }

    public static function prefix(): string
    {
        return (string) app_config('db.prefix', 'wp_');
    }

    public static function table(string $name): string
    {
        return self::prefix() . $name;
    }

    public static function rows(string $sql, string $types = '', array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        if (!$stmt) {
            return [];
        }

        if ($types !== '' && $params !== []) {
            $bind = [$types];
            foreach ($params as $key => $value) {
                $bind[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }

    public static function one(string $sql, string $types = '', array $params = []): array
    {
        return self::rows($sql, $types, $params)[0] ?? [];
    }

    public static function value(string $sql, string $types = '', array $params = []): mixed
    {
        $row = self::one($sql, $types, $params);
        return $row ? reset($row) : null;
    }

    public static function execute(string $sql, string $types = '', array $params = []): bool
    {
        $stmt = self::connection()->prepare($sql);
        if (!$stmt) {
            return false;
        }

        if ($types !== '' && $params !== []) {
            $bind = [$types];
            foreach ($params as $key => $value) {
                $bind[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }

        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public static function beginTransaction(): void
    {
        if (!self::connection()->begin_transaction()) {
            throw new \RuntimeException('No fue posible iniciar la transacción.');
        }
    }

    public static function commit(): void
    {
        if (!self::connection()->commit()) {
            throw new \RuntimeException('No fue posible confirmar la transacción.');
        }
    }

    public static function rollBack(): void
    {
        self::connection()->rollback();
    }

    public static function affectedRows(): int
    {
        return self::connection()->affected_rows;
    }

    public static function insert(string $table, array $data): int
    {
        if ($data === []) {
            return 0;
        }

        $columns = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $table . ' (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')';
        $types = str_repeat('s', count($columns));
        $params = array_map(static fn ($value): string => (string) $value, array_values($data));

        if (!self::execute($sql, $types, $params)) {
            return 0;
        }
        return (int) self::connection()->insert_id;
    }

    public static function update(string $table, array $data, int $id): bool
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "`{$column}` = ?";
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE _ID = ? LIMIT 1';
        $params = array_map(static fn ($value): string => (string) $value, array_values($data));
        $params[] = $id;

        return self::execute($sql, str_repeat('s', count($data)) . 'i', $params);
    }

    public static function tableExists(string $table): bool
    {
        if (array_key_exists($table, self::$tableExistsCache)) {
            return self::$tableExistsCache[$table];
        }

        return self::$tableExistsCache[$table] = (int) self::value(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            's',
            [$table]
        ) > 0;
    }

    public static function columns(string $table): array
    {
        if (array_key_exists($table, self::$columnsCache)) {
            return self::$columnsCache[$table];
        }

        try {
            $rows = self::rows('SHOW COLUMNS FROM ' . $table);
        } catch (Throwable) {
            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $columns[(string) $row['Field']] = true;
        }

        return self::$columnsCache[$table] = $columns;
    }

    public static function columnExists(string $table, string $column): bool
    {
        return isset(self::columns($table)[$column]);
    }
}
