<?php

declare(strict_types=1);

namespace Core;

final class View
{
    public static function make(string $name, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::renderFile(self::path($name), $data);

        if ($layout === null) {
            return $content;
        }

        $data['content'] = $content;

        return self::renderFile(self::path('layouts/' . $layout), $data);
    }

    public static function component(string $name, array $data = []): string
    {
        return self::renderFile(self::path('components/' . $name), $data);
    }

    public static function exists(string $name): bool
    {
        return is_file(self::path($name));
    }

    private static function path(string $name): string
    {
        $relative = str_replace('.', '/', $name) . '.php';

        return BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . $relative;
    }

    private static function renderFile(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new \RuntimeException('La vista no existe: ' . $file);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
