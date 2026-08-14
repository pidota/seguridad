<?php

declare(strict_types=1);

namespace Core;

final class Logger
{
    public static function error(\Throwable $e): void
    {
        self::write('ERROR', sprintf(
            '%s: %s in %s:%d',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        self::write('ERROR', $e->getTraceAsString());
    }

    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function warning(string $message): void
    {
        self::write('WARNING', $message);
    }

    private static function write(string $level, string $message): void
    {
        $dir = BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        $line = sprintf("[%s] %s: %s%s", date('Y-m-d H:i:s'), $level, $message, PHP_EOL);

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
