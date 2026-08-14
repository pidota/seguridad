<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\HttpException;
use Core\Response;
use Core\Session;
use Core\View;

abstract class Controller
{
    protected function view(string $name, array $data = [], ?string $layout = 'app'): void
    {
        echo View::make($name, $data, $layout);
    }

    protected function redirect(string $to): never
    {
        Response::redirect($to);
    }

    protected function back(): never
    {
        Response::back();
    }

    protected function json(array $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function failAndBack(\Throwable $e): never
    {
        if ($e instanceof HttpException && in_array($e->getStatusCode(), [403, 422], true)) {
            Session::flashAlert(
                $e->getStatusCode() === 403 ? 'warning' : 'error',
                'No se pudo completar la acción',
                $e->getMessage()
            );
            Response::back();
        }

        throw $e;
    }
}
