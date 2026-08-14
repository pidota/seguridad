<?php

declare(strict_types=1);

namespace App\Models\Cctv;

use Core\Model;

final class ShiftEquipmentCheck extends Model
{
    public const PHASE_OPENING = 'opening';
    public const PHASE_CLOSING = 'closing';

    public const STATUS_OPERATIONAL = 'operativo';
    public const STATUS_WITH_OBSERVATIONS = 'con_observaciones';
    public const STATUS_NON_OPERATIONAL = 'no_operativo';

    protected string $table = 'cctv_shift_equipment_checks';

    /**
     * @return list<string>
     */
    public static function phases(): array
    {
        return [
            self::PHASE_OPENING,
            self::PHASE_CLOSING,
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_OPERATIONAL,
            self::STATUS_WITH_OBSERVATIONS,
            self::STATUS_NON_OPERATIONAL,
        ];
    }

    public static function isValidPhase(string $phase): bool
    {
        return in_array($phase, self::phases(), true);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::statuses(), true);
    }
}
