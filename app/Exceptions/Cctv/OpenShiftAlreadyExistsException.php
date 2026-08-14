<?php

declare(strict_types=1);

namespace App\Exceptions\Cctv;

final class OpenShiftAlreadyExistsException extends \RuntimeException
{
    public function __construct(
        private readonly int $shiftId,
        string $message = 'Ya posee un turno CCTV abierto.'
    ) {
        parent::__construct($message, 422);
    }

    public function getShiftId(): int
    {
        return $this->shiftId;
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
