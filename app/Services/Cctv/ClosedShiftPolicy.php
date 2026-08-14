<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\Shift;
use App\Repositories\Cctv\ShiftRepository;
use Core\Auth;
use Core\Exceptions\HttpException;

final class ClosedShiftPolicy
{
    public function __construct(
        private readonly ShiftRepository $shifts = new ShiftRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $record
     */
    public function canActOnLogEntry(array $record, ?int $actorId = null): bool
    {
        if (hasPermission('cctv.log.view_all')) {
            return true;
        }

        $actorId ??= Auth::id();
        if ($actorId === null || $actorId < 1) {
            return false;
        }

        return (int) ($record['created_by'] ?? 0) === $actorId;
    }

    /**
     * @param array<string, mixed> $record
     */
    public function assertLogEntryOwnership(array $record, ?int $actorId = null): void
    {
        if ($this->canActOnLogEntry($record, $actorId)) {
            return;
        }

        throw new HttpException(403, 'No puede modificar registros de otros operadores.');
    }

    /**
     * @param array<string, mixed> $record
     */
    public function canCancelLogEntry(array $record): bool
    {
        if (!hasPermission('cctv.log.delete')) {
            return false;
        }

        if (!$this->canActOnLogEntry($record)) {
            return false;
        }

        $shiftId = (int) ($record['shift_id'] ?? $record['cctv_shift_id'] ?? 0);
        if ($shiftId < 1) {
            return false;
        }

        $shift = $this->shifts->findById($shiftId);
        if ($shift === null) {
            return false;
        }

        if (Shift::isClosed((string) ($shift['status'] ?? ''))) {
            return hasPermission('cctv.log.edit_closed');
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function assertLogEntryCancellation(int $shiftId, array $record, ?int $actorId = null): array
    {
        if (!hasPermission('cctv.log.delete')) {
            throw new HttpException(403, 'No puede anular registros de bitácora.');
        }

        $this->assertLogEntryOwnership($record, $actorId);

        if ($shiftId < 1) {
            throw new HttpException(422, 'La entrada no está asociada a un turno válido.');
        }

        $shift = $this->shifts->findById($shiftId);
        if ($shift === null) {
            throw new HttpException(404, 'El turno asociado ya no existe.');
        }

        if (Shift::isClosed((string) ($shift['status'] ?? ''))) {
            if (!hasPermission('cctv.log.edit_closed')) {
                throw new HttpException(
                    403,
                    'No puede anular registros pertenecientes a un turno cerrado.'
                );
            }

            return $shift;
        }

        return $shift;
    }

    /**
     * @param array<string, mixed> $record
     */
    public function canEditLogEntry(array $record): bool
    {
        if (!$this->canActOnLogEntry($record)) {
            return false;
        }

        $shiftId = (int) ($record['shift_id'] ?? $record['cctv_shift_id'] ?? 0);
        if ($shiftId < 1) {
            return false;
        }

        $shift = $this->shifts->findById($shiftId);
        if ($shift === null) {
            return false;
        }

        if (Shift::isClosed((string) ($shift['status'] ?? ''))) {
            return hasPermission('cctv.log.edit_closed');
        }

        return hasPermission('cctv.log.edit');
    }

    public function canEditShift(array $shift): bool
    {
        if (Shift::isClosed((string) ($shift['status'] ?? ''))) {
            return hasPermission('cctv.shifts.edit_closed');
        }

        return hasPermission('cctv.shifts.create') || hasPermission('cctv.shifts.close');
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function assertLogEntryMutation(int $shiftId, array $record, ?int $actorId = null): array
    {
        $this->assertLogEntryOwnership($record, $actorId);

        if ($shiftId < 1) {
            throw new HttpException(422, 'La entrada no está asociada a un turno válido.');
        }

        $shift = $this->shifts->findById($shiftId);
        if ($shift === null) {
            throw new HttpException(404, 'El turno asociado ya no existe.');
        }

        if (Shift::isClosed((string) ($shift['status'] ?? ''))) {
            if (!hasPermission('cctv.log.edit_closed')) {
                throw new HttpException(
                    403,
                    'No puede modificar registros pertenecientes a un turno cerrado.'
                );
            }

            return $shift;
        }

        if (!hasPermission('cctv.log.edit')) {
            throw new HttpException(403, 'No puede modificar registros de bitácora.');
        }

        return $shift;
    }

    /**
     * @param array<string, mixed> $shift
     */
    public function assertShiftMutation(array $shift): void
    {
        if (!Shift::isClosed((string) ($shift['status'] ?? ''))) {
            return;
        }

        if (!hasPermission('cctv.shifts.edit_closed')) {
            throw new HttpException(
                403,
                'No puede modificar un turno cerrado.'
            );
        }
    }

    public function isShiftClosed(int $shiftId): bool
    {
        if ($shiftId < 1) {
            return false;
        }

        $shift = $this->shifts->findById($shiftId);

        return $shift !== null && Shift::isClosed((string) ($shift['status'] ?? ''));
    }
}
