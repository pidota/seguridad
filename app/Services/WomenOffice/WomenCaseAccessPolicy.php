<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use Core\Auth;
use Core\Exceptions\HttpException;

final class WomenCaseAccessPolicy
{
    /**
     * @param array<string, mixed> $case
     */
    public function canAccess(array $case): bool
    {
        if (hasPermission('women.cases.view_all')) {
            return true;
        }

        return (int) ($case['created_by'] ?? 0) === (int) (Auth::id() ?? 0);
    }

    /**
     * @param array<string, mixed> $case
     */
    public function assertCanView(array $case): void
    {
        if (!$this->canAccess($case)) {
            throw new HttpException(403, 'No tiene permiso para consultar este caso.');
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    public function canEdit(array $case): bool
    {
        if (!$this->canAccess($case)) {
            return false;
        }

        $closed = !empty($case['cancelled_at'])
            || !empty($case['closed_at'])
            || CaseStatus::isClosedSlug((string) ($case['case_status_slug'] ?? ''));

        if ($closed) {
            return hasPermission('women.cases.edit_closed');
        }

        return hasPermission('women.cases.edit');
    }

    /**
     * @param array<string, mixed> $case
     */
    public function assertCanEdit(array $case): void
    {
        $this->assertCanView($case);

        if (!$this->canEdit($case)) {
            throw new HttpException(403, 'No tiene permiso para modificar este caso.');
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    public function assertCanClose(array $case): void
    {
        $this->assertCanView($case);

        if ($this->isTerminal($case)) {
            throw new HttpException(403, 'El caso ya está finalizado o anulado.');
        }

        if (!hasPermission('women.cases.close')) {
            throw new HttpException(403, 'No tiene permiso para finalizar casos.');
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    public function assertCanCancel(array $case): void
    {
        $this->assertCanView($case);

        if ($this->isTerminal($case)) {
            throw new HttpException(403, 'El caso ya está finalizado o anulado.');
        }

        if (!hasPermission('women.cases.close')) {
            throw new HttpException(403, 'No tiene permiso para anular casos.');
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    public function isTerminal(array $case): bool
    {
        return !empty($case['cancelled_at'])
            || !empty($case['closed_at'])
            || CaseStatus::isClosedSlug((string) ($case['case_status_slug'] ?? ''));
    }
}
