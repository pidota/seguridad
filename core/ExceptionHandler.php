<?php

declare(strict_types=1);

namespace Core;

final class ExceptionHandler
{
    public static function register(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (\Throwable $e): void {
            self::report($e);

            if (!headers_sent()) {
                http_response_code(500);
            }

            $debug = (bool) config('app.debug', false);

            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, $e->getMessage() . PHP_EOL);
                exit(1);
            }

            echo View::make('errors/500', [
                'title' => '500 — Error interno',
                'message' => $debug ? $e->getMessage() : 'Ha ocurrido un error interno.',
                'status' => 500,
            ], 'error');
            exit;
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();

            if ($error === null) {
                return;
            }

            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

            if (!in_array($error['type'], $fatal, true)) {
                return;
            }

            self::report(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        });
    }

    public static function report(\Throwable $e): void
    {
        Logger::error($e);
    }
}
