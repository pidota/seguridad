<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\HttpException;

final class App
{
    private static ?self $instance = null;
    private Router $router;
    private Request $request;

    public function __construct()
    {
        self::$instance = $this;
        Env::load(BASE_PATH . DIRECTORY_SEPARATOR . '.env');
        $this->configurePhp();
        ExceptionHandler::register();
        Session::start();
        $this->request = Request::capture();
        $this->router = new Router();
    }

    public function run(): void
    {
        $this->sendSecurityHeaders();
        $this->registerMiddleware();
        $this->loadRoutes();

        try {
            $this->enforceCsrf();
            $this->router->dispatch($this->request);
        } catch (HttpException $e) {
            $this->renderHttpError($e);
        } catch (\Throwable $e) {
            ExceptionHandler::report($e);
            $message = (bool) config('app.debug', false)
                ? $e->getMessage()
                : 'Error interno del servidor.';
            $this->renderHttpError(new HttpException(500, $message));
        }
    }

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            throw new \RuntimeException('La aplicación no ha sido inicializada.');
        }

        return self::$instance;
    }

    public function router(): Router
    {
        return $this->router;
    }

    private function configurePhp(): void
    {
        date_default_timezone_set((string) config('app.timezone', 'America/Santiago'));

        $debug = (bool) config('app.debug', false);

        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);
    }

    private function sendSecurityHeaders(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }

    private function registerMiddleware(): void
    {
        $this->router->alias('auth', \App\Middleware\AuthMiddleware::class);
        $this->router->alias('guest', \App\Middleware\GuestMiddleware::class);
        $this->router->alias('csrf', \App\Middleware\CsrfMiddleware::class);
        $this->router->alias('role', \App\Middleware\RoleMiddleware::class);
        $this->router->alias('can', \App\Middleware\PermissionMiddleware::class);
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.php';
    }

    private function enforceCsrf(): void
    {
        if (!in_array($this->request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        (new \App\Middleware\CsrfMiddleware())->handle($this->request);
    }

    private function renderHttpError(HttpException $e): void
    {
        http_response_code($e->getStatusCode());

        $view = match ($e->getStatusCode()) {
            403 => 'errors/403',
            404 => 'errors/404',
            419 => 'errors/419',
            default => 'errors/500',
        };

        echo View::make($view, [
            'title' => $e->getStatusCode() . ' — ' . config('app.name'),
            'message' => $e->getMessage(),
            'status' => $e->getStatusCode(),
        ], 'error');
    }
}
