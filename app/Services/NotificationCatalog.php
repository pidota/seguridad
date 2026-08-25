<?php

declare(strict_types=1);

namespace App\Services;

final class NotificationCatalog
{
    public const TYPE_MEETING_SIGNATURE_PENDING = 'meeting_signature_pending';
    public const TYPE_MEETING_SIGNED_COMPLETE = 'meeting_signed_complete';
    public const TYPE_MEETING_CORRECTION = 'meeting_correction_requested';
    public const TYPE_MEETING_CANCELLED = 'meeting_cancelled';
    public const TYPE_MEETING_REOPENED = 'meeting_reopened';
    public const TYPE_MEETING_ATTENDANCE_CONFIRMED = 'meeting_attendance_confirmed';
    public const TYPE_MEETING_ATTENDANCE_DECLINED = 'meeting_attendance_declined';
    public const TYPE_SENDA_FOLLOWUP_DUE = 'senda_followup_due';
    public const TYPE_SENDA_FOLLOWUP_OVERDUE = 'senda_followup_overdue';
    public const TYPE_SENDA_REFERRAL_CREATED = 'senda_referral_created';
    public const TYPE_WOMEN_REFERRAL_CREATED = 'women_referral_created';
    public const TYPE_WOMEN_FOLLOWUP_DUE = 'women_followup_due';

    /**
     * @return array{icon: string, tone: string, module: string}
     */
    public static function meta(string $type): array
    {
        return match ($type) {
            self::TYPE_MEETING_SIGNATURE_PENDING => ['icon' => 'bi-pen', 'tone' => 'warning', 'module' => 'Reuniones'],
            self::TYPE_MEETING_SIGNED_COMPLETE => ['icon' => 'bi-check2-circle', 'tone' => 'success', 'module' => 'Reuniones'],
            self::TYPE_MEETING_CORRECTION => ['icon' => 'bi-arrow-repeat', 'tone' => 'warning', 'module' => 'Reuniones'],
            self::TYPE_MEETING_CANCELLED => ['icon' => 'bi-x-circle', 'tone' => 'danger', 'module' => 'Reuniones'],
            self::TYPE_MEETING_REOPENED => ['icon' => 'bi-arrow-counterclockwise', 'tone' => 'info', 'module' => 'Reuniones'],
            self::TYPE_MEETING_ATTENDANCE_CONFIRMED,
            self::TYPE_MEETING_ATTENDANCE_DECLINED => ['icon' => 'bi-person-check', 'tone' => 'info', 'module' => 'Reuniones'],
            self::TYPE_SENDA_FOLLOWUP_DUE,
            self::TYPE_SENDA_FOLLOWUP_OVERDUE => ['icon' => 'bi-calendar-event', 'tone' => 'warning', 'module' => 'SENDA'],
            self::TYPE_SENDA_REFERRAL_CREATED => ['icon' => 'bi-file-earmark-medical', 'tone' => 'info', 'module' => 'SENDA'],
            self::TYPE_WOMEN_REFERRAL_CREATED,
            self::TYPE_WOMEN_FOLLOWUP_DUE => ['icon' => 'bi-heart-pulse', 'tone' => 'info', 'module' => 'Oficina de la Mujer'],
            default => ['icon' => 'bi-bell', 'tone' => 'other', 'module' => 'Sistema'],
        };
    }

    public static function url(array $notification): ?string
    {
        $type = (string) ($notification['type'] ?? '');
        $relatedId = (int) ($notification['related_id'] ?? 0);

        return match ($type) {
            self::TYPE_MEETING_SIGNATURE_PENDING => hasPermission('meetings.view_pending_signatures')
                ? url('/meetings/pending-signatures')
                : ($relatedId > 0 && hasPermission('meetings.view') ? url('/meetings/' . $relatedId) : null),
            self::TYPE_MEETING_SIGNED_COMPLETE,
            self::TYPE_MEETING_CORRECTION,
            self::TYPE_MEETING_CANCELLED,
            self::TYPE_MEETING_REOPENED,
            self::TYPE_MEETING_ATTENDANCE_CONFIRMED,
            self::TYPE_MEETING_ATTENDANCE_DECLINED => $relatedId > 0 && hasPermission('meetings.view')
                ? url('/meetings/' . $relatedId)
                : null,
            self::TYPE_SENDA_FOLLOWUP_DUE => hasPermission('senda.followups.view')
                ? url('/senda/follow-ups?status=pending')
                : null,
            self::TYPE_SENDA_FOLLOWUP_OVERDUE => hasPermission('senda.followups.view')
                ? url('/senda/follow-ups?status=overdue')
                : null,
            self::TYPE_SENDA_REFERRAL_CREATED => $relatedId > 0 && hasPermission('senda.referrals.view')
                ? url('/senda/referrals/' . $relatedId)
                : (hasPermission('senda.referrals.view') ? url('/senda/referrals') : null),
            self::TYPE_WOMEN_REFERRAL_CREATED,
            self::TYPE_WOMEN_FOLLOWUP_DUE => $relatedId > 0 && hasPermission('women.cases.view')
                ? url('/women/cases/' . $relatedId)
                : (hasPermission('women.cases.view') ? url('/women/cases') : null),
            default => null,
        };
    }
}
