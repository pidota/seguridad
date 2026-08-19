<?php

declare(strict_types=1);

namespace App\Services\Meetings;

final class MeetingStatus
{
    public const DRAFT = 'draft';
    public const PENDING_SIGNATURES = 'pending_signatures';
    public const PARTIALLY_SIGNED = 'partially_signed';
    public const SIGNED = 'signed';
    public const CORRECTION_REQUESTED = 'correction_requested';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function reopenableSlugs(): array
    {
        return [
            self::PENDING_SIGNATURES,
            self::PARTIALLY_SIGNED,
            self::CORRECTION_REQUESTED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function cancellableSlugs(): array
    {
        return [
            self::DRAFT,
            self::PENDING_SIGNATURES,
            self::PARTIALLY_SIGNED,
            self::CORRECTION_REQUESTED,
        ];
    }

    public static function isReopenable(string $slug): bool
    {
        return in_array($slug, self::reopenableSlugs(), true);
    }

    public static function isCancellable(string $slug): bool
    {
        return in_array($slug, self::cancellableSlugs(), true);
    }

    /**
     * @return list<string>
     */
    public static function editableSlugs(): array
    {
        return [self::DRAFT];
    }

    public static function isEditable(string $slug): bool
    {
        return in_array($slug, self::editableSlugs(), true);
    }

    public static function isTerminal(string $slug): bool
    {
        return in_array($slug, [self::SIGNED, self::CANCELLED], true);
    }

    public static function label(string $slug): string
    {
        return match ($slug) {
            self::DRAFT => 'Borrador',
            self::PENDING_SIGNATURES => 'Pendiente de Firmas',
            self::PARTIALLY_SIGNED => 'Firmado Parcialmente',
            self::SIGNED => 'Firmado',
            self::CORRECTION_REQUESTED => 'Corrección Solicitada',
            self::CANCELLED => 'Anulado',
            default => '—',
        };
    }
}
