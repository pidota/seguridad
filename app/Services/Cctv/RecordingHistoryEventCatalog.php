<?php

declare(strict_types=1);

namespace App\Services\Cctv;

final class RecordingHistoryEventCatalog
{
    public const STATUS_CHANGE = 'status_change';
    public const COMPLAINT_REGISTERED = 'complaint_registered';
    public const COMPLAINT_VERIFIED = 'complaint_verified';
    public const PRESERVED = 'recording_preserved';
    public const ASSIGNED = 'assigned';
    public const CANCELLED = 'cancelled';
    public const DELIVERED = 'delivered';

    public static function label(string $eventType, string $statusLabel = ''): string
    {
        return match ($eventType) {
            self::COMPLAINT_REGISTERED => 'Denuncia presentada',
            self::COMPLAINT_VERIFIED => 'Denuncia verificada',
            self::PRESERVED => 'Grabación preservada',
            self::ASSIGNED => 'Responsable asignado',
            self::CANCELLED => 'Solicitud anulada',
            self::DELIVERED => 'Grabación entregada',
            default => $statusLabel !== '' ? $statusLabel : 'Cambio de estado',
        };
    }
}
