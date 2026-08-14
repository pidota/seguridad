<?php

declare(strict_types=1);

namespace Core\Exceptions;

final class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode), $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            403 => 'No tiene permisos para acceder a este recurso.',
            404 => 'La página solicitada no existe.',
            405 => 'Método no permitido.',
            419 => 'La sesión ha expirado. Vuelva a intentar.',
            500 => 'Error interno del servidor.',
            default => 'Ha ocurrido un error.',
        };
    }
}
