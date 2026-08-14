<?php

declare(strict_types=1);

namespace Core;

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefixes = [
                'Core\\' => BASE_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR,
                'App\\'  => BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR,
            ];

            foreach ($prefixes as $prefix => $baseDir) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }

                $relative = substr($class, strlen($prefix));
                $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

                if (is_file($file)) {
                    require $file;
                    return;
                }
            }
        });
    }
}
