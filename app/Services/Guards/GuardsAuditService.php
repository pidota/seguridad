<?php

declare(strict_types=1);

namespace App\Services\Guards;

use App\Services\AuditService;

final class GuardsAuditService
{
    public function __construct(private readonly AuditService $audit = new AuditService())
    {
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function shiftOpened(int $id, array $snapshot): void
    {
        $this->audit->log(
            AuditService::ACTION_CREATED,
            AuditService::MODULE_GUARDS,
            AuditService::RESOURCE_GUARDS_SHIFT,
            $id,
            null,
            $snapshot
        );
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function shiftClosed(int $id, array $before, array $after): void
    {
        $this->audit->log(
            AuditService::ACTION_UPDATED,
            AuditService::MODULE_GUARDS,
            AuditService::RESOURCE_GUARDS_SHIFT,
            $id,
            $before,
            $after
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function logEntryCreated(int $id, array $snapshot): void
    {
        $this->audit->log(
            AuditService::ACTION_CREATED,
            AuditService::MODULE_GUARDS,
            AuditService::RESOURCE_GUARDS_LOG_ENTRY,
            $id,
            null,
            $snapshot
        );
    }
}
