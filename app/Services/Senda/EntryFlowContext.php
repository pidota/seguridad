<?php

declare(strict_types=1);

namespace App\Services\Senda;

use Core\Session;

/**
 * Estado del flujo operativo Atención: RUT → tipos → ficha de referencia → registro.
 */
final class EntryFlowContext
{
    public const REFERRAL_ASKED_KEY = 'senda_entry_referral_asked';
    public const REFERRAL_REQUIRED_KEY = 'senda_entry_referral_required';
    public const REFERRAL_COMPLETED_KEY = 'senda_entry_referral_completed';
    public const DRAFT_ATTENTION_KEY = 'senda_entry_draft_attention_id';

    public static function forget(): void
    {
        foreach ([
            self::REFERRAL_ASKED_KEY,
            self::REFERRAL_REQUIRED_KEY,
            self::REFERRAL_COMPLETED_KEY,
            self::DRAFT_ATTENTION_KEY,
        ] as $key) {
            Session::forget($key);
        }
    }

    public static function needsReferralQuestion(): bool
    {
        if (!hasPermission('senda.referrals.create')) {
            return false;
        }

        return !Session::get(self::REFERRAL_ASKED_KEY, false);
    }

    public static function markReferralSkipped(): void
    {
        Session::put(self::REFERRAL_ASKED_KEY, true);
        Session::put(self::REFERRAL_REQUIRED_KEY, false);
        Session::forget(self::REFERRAL_COMPLETED_KEY);
        Session::forget(self::DRAFT_ATTENTION_KEY);
    }

    public static function markReferralRequired(int $draftAttentionId): void
    {
        Session::put(self::REFERRAL_ASKED_KEY, true);
        Session::put(self::REFERRAL_REQUIRED_KEY, true);
        Session::put(self::REFERRAL_COMPLETED_KEY, false);
        Session::put(self::DRAFT_ATTENTION_KEY, $draftAttentionId);
    }

    public static function markReferralCompleted(): void
    {
        Session::put(self::REFERRAL_COMPLETED_KEY, true);
    }

    public static function referralRequired(): bool
    {
        return (bool) Session::get(self::REFERRAL_REQUIRED_KEY, false);
    }

    public static function referralCompleted(): bool
    {
        if (!self::referralRequired()) {
            return true;
        }

        return (bool) Session::get(self::REFERRAL_COMPLETED_KEY, false);
    }

    public static function draftAttentionId(): ?int
    {
        $id = Session::get(self::DRAFT_ATTENTION_KEY);

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    public static function clearDraftAttention(): void
    {
        Session::forget(self::DRAFT_ATTENTION_KEY);
    }

    public static function attentionCreateUrl(): string
    {
        return url('/senda/attentions/create');
    }

    public static function attentionTypesUrl(): string
    {
        return url('/senda') . '?next=attention';
    }

    public static function referralQuestionUrl(): string
    {
        return url('/senda') . '?next=attention&step=referral';
    }

    public static function isEntryFlow(string $flow): bool
    {
        return trim($flow) === 'entry';
    }
}
