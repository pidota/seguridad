<?php

declare(strict_types=1);

namespace Core;

final class Database
{
    private static ?\PDO $connection = null;

    private function __construct()
    {
    }

    public static function connection(): \PDO
    {
        if (self::$connection instanceof \PDO) {
            return self::$connection;
        }

        $config = require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$connection = new \PDO($dsn, $config['username'], $config['password'], $config['options']);

        return self::$connection;
    }

    /**
     * @template T
     * @param callable(\PDO): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }
}
