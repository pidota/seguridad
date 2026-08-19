<?php

declare(strict_types=1);

namespace App\Services\Meetings;

final class MeetingSourceModule
{
    public const SENDA = 'senda';
    public const CCTV = 'cctv';
    public const WOMEN = 'women';
    public const GUARDS = 'guards';
    public const ADMIN = 'admin';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::SENDA,
            self::CCTV,
            self::WOMEN,
            self::GUARDS,
            self::ADMIN,
        ];
    }

    public static function label(string $slug): string
    {
        return match ($slug) {
            self::SENDA => 'SENDA',
            self::CCTV => 'Central de Cámaras',
            self::WOMEN => 'Oficina de la Mujer',
            self::GUARDS => 'Guardias',
            self::ADMIN => 'Administración',
            default => strtoupper($slug),
        };
    }
}
