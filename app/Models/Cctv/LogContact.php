<?php

declare(strict_types=1);

namespace App\Models\Cctv;

use Core\Model;

final class LogContact extends Model
{
    public const TYPE_CARABINEROS = 'carabineros';
    public const TYPE_SEGURIDAD_MUNICIPAL = 'seguridad_municipal';
    public const TYPE_GUARDIAS_MUNICIPALES = 'guardias_municipales';
    public const TYPE_BOMBEROS = 'bomberos';
    public const TYPE_SAMU = 'samu';
    public const TYPE_PDI = 'pdi';
    public const TYPE_OTHER = 'otro';

    protected string $table = 'cctv_log_contacts';

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_CARABINEROS,
            self::TYPE_SEGURIDAD_MUNICIPAL,
            self::TYPE_GUARDIAS_MUNICIPALES,
            self::TYPE_BOMBEROS,
            self::TYPE_SAMU,
            self::TYPE_PDI,
            self::TYPE_OTHER,
        ];
    }

    public static function isValidType(string $type): bool
    {
        return in_array($type, self::types(), true);
    }
}
