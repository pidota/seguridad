<?php

declare(strict_types=1);

namespace App\Services\Senda;

final class FollowUpStatus
{
    public const DUE_TODAY = 'due_today';
    public const OVERDUE = 'overdue';
    public const DONE_TODAY = 'done_today';
    public const PENDING = 'pending';

    /**
     * @return list<string>
     */
    public static function countableKeys(): array
    {
        return [self::DUE_TODAY, self::OVERDUE, self::DONE_TODAY, self::PENDING];
    }

    /**
     * @return list<string>
     */
    public static function dashboardKeys(): array
    {
        return [self::DONE_TODAY, self::PENDING, self::OVERDUE];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::countableKeys(), true);
    }

    public static function usesTodayParam(string $status): bool
    {
        return in_array($status, [self::DUE_TODAY, self::OVERDUE, self::DONE_TODAY], true);
    }

    public static function today(?string $today = null): string
    {
        if ($today !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $today) === 1) {
            return $today;
        }

        return date('Y-m-d');
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DUE_TODAY => 'Seguimientos para hoy',
            self::OVERDUE => 'Seguimientos atrasados',
            self::DONE_TODAY => 'Seguimientos realizados hoy',
            self::PENDING => 'Seguimientos pendientes',
            default => 'Seguimiento',
        };
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::OVERDUE => 'overdue',
            self::DUE_TODAY => 'due',
            self::DONE_TODAY => 'done',
            self::PENDING => 'due',
            default => 'due',
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function isPending(array $row): bool
    {
        return self::requiresFollowUp($row) && self::nextDate($row) !== null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function isDueToday(array $row, ?string $today = null): bool
    {
        $next = self::nextDate($row);

        return self::isPending($row) && $next === self::today($today);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function isOverdue(array $row, ?string $today = null): bool
    {
        $next = self::nextDate($row);

        return self::isPending($row) && $next !== null && $next < self::today($today);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function isDoneOn(array $row, ?string $date = null): bool
    {
        $done = substr(trim((string) ($row['follow_up_date'] ?? '')), 0, 10);

        return $done === self::today($date);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function requiresFollowUp(array $row): bool
    {
        $value = $row['requires_follow_up'] ?? 0;

        if (in_array($value, ['si', 1, '1', true], true)) {
            return true;
        }

        return !in_array($value, ['no', 0, '0', false, null, ''], true) && (int) $value === 1;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nextDate(array $row): ?string
    {
        $value = substr(trim((string) ($row['next_follow_up_date'] ?? '')), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /**
     * SQL de coincidencia. Usa el alias de senda_follow_ups y el parámetro :status_today.
     */
    public static function matchSql(string $status, string $alias = 'f', string $todayParam = ':status_today'): string
    {
        $pending = self::pendingSql($alias);

        $sql = match ($status) {
            self::OVERDUE => $pending . " AND {$alias}.next_follow_up_date < {$todayParam}",
            self::DUE_TODAY => $pending . " AND {$alias}.next_follow_up_date = {$todayParam}",
            self::PENDING => $pending,
            self::DONE_TODAY => "{$alias}.follow_up_date = {$todayParam}",
            default => '1 = 0',
        };

        if (in_array($status, [self::OVERDUE, self::DUE_TODAY, self::PENDING], true)) {
            $sql .= ' AND ' . self::latestPerAttentionSql($alias);
        }

        return $sql;
    }

    public static function pendingSql(string $alias = 'f'): string
    {
        return "{$alias}.requires_follow_up = 1 AND {$alias}.next_follow_up_date IS NOT NULL";
    }

    public static function latestPerAttentionSql(string $alias = 'f'): string
    {
        $latest = $alias . '_latest';

        return "{$alias}.id = (
            SELECT {$latest}.id
            FROM senda_follow_ups {$latest}
            WHERE {$latest}.senda_attention_id = {$alias}.senda_attention_id
              AND {$latest}.deleted_at IS NULL
            ORDER BY {$latest}.follow_up_date DESC, {$latest}.follow_up_time DESC, {$latest}.id DESC
            LIMIT 1
        )";
    }
}
