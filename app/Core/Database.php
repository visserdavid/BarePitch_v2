<?php

declare(strict_types=1);

namespace BarePitch\Core;

class Database
{
    private static ?\PDO $connection = null;

    public static function connect(): \PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host   = getenv('DB_HOST')     ?: 'localhost';
        $name   = getenv('DB_NAME')     ?: '';
        $user   = getenv('DB_USER')     ?: '';
        $pass   = getenv('DB_PASSWORD') ?: '';

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

        self::$connection = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$connection;
    }

    public static function connection(): \PDO
    {
        if (self::$connection === null) {
            throw new \RuntimeException('Database connection has not been established. Call Database::connect() first.');
        }

        return self::$connection;
    }

    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::connection()->commit();
    }

    public static function rollback(): void
    {
        self::connection()->rollBack();
    }
}
