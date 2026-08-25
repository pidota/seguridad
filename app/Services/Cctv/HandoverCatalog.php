<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogHandover;

final class HandoverCatalog
{
    /**
     * @return list<string>
     */
    public static function declineReasons(): array
    {
        return [
            'finished_during_handover' => 'Procedimiento finalizado durante cambio de turno',
            'derived_no_cctv_followup' => 'Procedimiento derivado y ya no requiere seguimiento CCTV',
            'external_assumed' => 'Institución externa asumió procedimiento',
            'situation_normalized' => 'Situación normalizada',
            'no_pending_actions' => 'No existen nuevas acciones pendientes',
            'duplicate' => 'Registro duplicado',
            'other' => 'Otro',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function declineReasonOptions(): array
    {
        $options = [];
        foreach (self::declineReasons() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function delegatedInstitutions(): array
    {
        return [
            ['value' => 'carabineros', 'label' => 'Carabineros'],
            ['value' => 'seguridad_municipal', 'label' => 'Seguridad Municipal'],
            ['value' => 'guardias_municipales', 'label' => 'Guardias Municipales'],
            ['value' => 'bomberos', 'label' => 'Bomberos'],
            ['value' => 'samu', 'label' => 'SAMU'],
            ['value' => 'pdi', 'label' => 'PDI'],
            ['value' => 'other', 'label' => 'Otro'],
        ];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            LogHandover::STATUS_PENDING => 'Pendiente de revisión',
            LogHandover::STATUS_ACCEPTED => 'Continuidad aceptada',
            LogHandover::STATUS_NOT_CONTINUED => 'No se continuará',
            LogHandover::STATUS_COMPLETED_BEFORE_REVIEW => 'Finalizado antes de revisión',
            default => $status !== '' ? $status : '—',
        };
    }

    public static function declineReasonLabel(string $reason): string
    {
        return self::declineReasons()[$reason] ?? $reason;
    }

    public static function institutionLabel(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        foreach (self::delegatedInstitutions() as $item) {
            if ($item['value'] === $value) {
                return $item['label'];
            }
        }

        return $value;
    }

    public static function isValidDeclineReason(string $reason): bool
    {
        return array_key_exists($reason, self::declineReasons());
    }
}
