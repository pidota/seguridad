<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Repositories\Meetings\MeetingRepository;
use App\Repositories\Meetings\MeetingSignatureRepository;
use App\Services\AuditService;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Request;

final class MeetingSignatureService
{
    public function __construct(
        private readonly MeetingRepository $meetings = new MeetingRepository(),
        private readonly MeetingSignatureRepository $signatures = new MeetingSignatureRepository(),
        private readonly MeetingService $meetingService = new MeetingService(),
        private readonly UserSignatureService $userSignatures = new UserSignatureService(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly MeetingAuditService $audit = new MeetingAuditService()
    ) {
    }

    public function getPendingCountForUser(?int $userId = null): int
    {
        $userId = $userId ?? Auth::id();
        if ($userId === null) {
            return 0;
        }

        if (!hasPermission('meetings.view_pending_signatures')) {
            return 0;
        }

        return $this->signatures->countPendingForUser($userId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingMeetingsForUser(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        if ($userId === null) {
            return [];
        }

        return array_map(function (array $row): array {
            $row['status_label'] = MeetingStatus::label((string) ($row['status'] ?? ''));
            $row['source_module_label'] = MeetingSourceModule::label((string) ($row['source_module'] ?? ''));
            $row['signatures_label'] = (int) ($row['signatures_signed'] ?? 0) . '/' . (int) ($row['signatures_total'] ?? 0);

            return $row;
        }, $this->signatures->pendingMeetingsForUser($userId));
    }

    public function finalize(int $meetingId): void
    {
        $meeting = $this->meetingService->findDetailed($meetingId);
        $this->meetingService->assertCanEdit($meeting);

        $signers = $this->requiredSigners($meeting['participants'] ?? []);
        if ($signers === []) {
            throw new HttpException(422, 'Debe incluir al menos un participante interno que requiera firma.');
        }

        $hashService = new MeetingContentHashService();
        $contentHash = $hashService->compute($meeting);

        $pdo = Database::connection();
        $started = $pdo->inTransaction();
        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->meetings->finalize($meetingId, $contentHash);

            foreach ($signers as $signer) {
                $this->signatures->create([
                    'meeting_id' => $meetingId,
                    'participant_id' => (int) $signer['participant_id'],
                    'user_id' => (int) $signer['user_id'],
                    'status' => 'pending',
                ]);

                $this->notifications->notifySignaturePending(
                    (int) $signer['user_id'],
                    $meetingId,
                    (string) ($meeting['meeting_number'] ?? '')
                );
            }

            $after = $this->meetingService->findDetailed($meetingId);
            $this->audit->finalized($meetingId, $this->auditSnapshot($meeting), $this->auditSnapshot($after));

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof HttpException) {
                throw $e;
            }

            throw new HttpException(500, 'No fue posible finalizar la reunión.');
        }
    }

    public function sign(int $meetingId): void
    {
        if (!hasPermission('meetings.sign')) {
            throw new HttpException(403, 'No tiene permiso para firmar reuniones.');
        }

        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $meeting = $this->meetingService->findDetailed($meetingId);
        $this->assertSignableMeeting($meeting);

        $pending = $this->signatures->findPendingForUser($meetingId, $userId);
        if ($pending === null) {
            throw new HttpException(403, 'No tiene una solicitud de firma pendiente para esta reunión.');
        }

        $hashService = new MeetingContentHashService();
        $currentHash = $hashService->compute($meeting);
        if ((string) ($meeting['content_hash'] ?? '') !== $currentHash) {
            throw new HttpException(409, 'El contenido del registro cambió. Solicite una nueva versión antes de firmar.');
        }

        $userSignature = $this->userSignatures->assertActive($userId);
        $snapshotPath = $this->userSignatures->copySnapshot(
            (string) $userSignature['image_path'],
            $meetingId,
            $userId
        );

        $signed = $this->signatures->markSigned((int) $pending['id'], [
            'status' => 'signed',
            'signature_snapshot_path' => $snapshotPath,
            'signed_at' => date('Y-m-d H:i:s'),
            'signed_ip' => Request::capture()->ip(),
            'content_hash_at_signing' => $currentHash,
        ]);

        if (!$signed) {
            throw new HttpException(409, 'No fue posible registrar la firma. Intente nuevamente.');
        }

        $this->audit->signed($meetingId, [
            'user_id' => $userId,
            'signature_id' => (int) $pending['id'],
        ]);

        $this->refreshMeetingStatus($meetingId);
    }

    public function requestCorrection(int $meetingId, string $reason): void
    {
        if (!hasPermission('meetings.sign')) {
            throw new HttpException(403, 'No tiene permiso para solicitar corrección.');
        }

        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new HttpException(422, 'Indique un motivo de al menos 10 caracteres.');
        }

        $meeting = $this->meetingService->findDetailed($meetingId);
        $this->assertSignableMeeting($meeting);

        $pending = $this->signatures->findPendingForUser($meetingId, $userId);
        if ($pending === null) {
            throw new HttpException(403, 'No tiene una solicitud de firma pendiente para esta reunión.');
        }

        $this->signatures->markRejected((int) $pending['id'], $reason);
        $this->meetings->markCorrectionRequested($meetingId);
        $this->notifications->notifyCorrectionRequested(
            (int) ($meeting['created_by'] ?? 0),
            $meetingId,
            (string) ($meeting['meeting_number'] ?? ''),
            $reason
        );

        $this->audit->correctionRequested($meetingId, [
            'user_id' => $userId,
            'reason_excerpt' => mb_substr($reason, 0, 120),
        ]);
    }

    /**
     * @param array<string, mixed> $meeting
     */
    public function presentSignatures(array $meeting): array
    {
        $rows = $this->signatures->forMeeting((int) ($meeting['id'] ?? 0));
        foreach ($rows as &$row) {
            $row['status_label'] = match ((string) ($row['status'] ?? '')) {
                'signed' => 'Firmado',
                'rejected' => 'Corrección solicitada',
                default => 'Pendiente',
            };
            $row['signed_at_label'] = !empty($row['signed_at'])
                ? date('d-m-Y H:i', strtotime((string) $row['signed_at']))
                : null;
        }
        unset($row);

        return $rows;
    }

    public function canUserSign(int $meetingId, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if ($userId === null || !hasPermission('meetings.sign')) {
            return false;
        }

        return $this->signatures->findPendingForUser($meetingId, $userId) !== null;
    }

    public function findSignatureForImage(int $signatureId): array
    {
        $row = $this->signatures->findById($signatureId);
        if ($row === null || ($row['status'] ?? '') !== 'signed' || empty($row['signature_snapshot_path'])) {
            throw new HttpException(404, 'Firma no encontrada.');
        }

        return $row;
    }

    public function refreshMeetingStatus(int $meetingId): void
    {
        $counts = $this->signatures->countsForMeeting($meetingId);
        if ($counts['total'] === 0) {
            return;
        }

        if ($counts['pending'] === 0) {
            $this->meetings->updateStatus($meetingId, MeetingStatus::SIGNED, date('Y-m-d H:i:s'));
            $meeting = $this->meetings->findById($meetingId);
            if ($meeting !== null) {
                $this->notifications->notifyMeetingCompleted(
                    (int) ($meeting['created_by'] ?? 0),
                    $meetingId,
                    (string) ($meeting['meeting_number'] ?? '')
                );
            }

            return;
        }

        if ($counts['signed'] > 0) {
            $this->meetings->updateStatus($meetingId, MeetingStatus::PARTIALLY_SIGNED);

            return;
        }

        $this->meetings->updateStatus($meetingId, MeetingStatus::PENDING_SIGNATURES);
    }

    /**
     * @param list<array<string, mixed>> $participants
     * @return list<array{participant_id: int, user_id: int}>
     */
    private function requiredSigners(array $participants): array
    {
        $signers = [];
        foreach ($participants as $participant) {
            if (($participant['participant_type'] ?? '') !== 'internal') {
                continue;
            }

            if (empty($participant['signature_required'])) {
                continue;
            }

            $userId = (int) ($participant['user_id'] ?? 0);
            $participantId = (int) ($participant['id'] ?? 0);
            if ($userId < 1 || $participantId < 1) {
                continue;
            }

            $signers[] = [
                'participant_id' => $participantId,
                'user_id' => $userId,
            ];
        }

        return $signers;
    }

    /**
     * @param array<string, mixed> $meeting
     */
    private function assertSignableMeeting(array $meeting): void
    {
        $status = (string) ($meeting['status'] ?? '');
        if (in_array($status, [MeetingStatus::CANCELLED, MeetingStatus::DRAFT], true)) {
            throw new HttpException(403, 'Esta reunión no admite firmas en su estado actual.');
        }

        $this->meetingService->assertCanView($meeting);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        return $this->audit->sanitize(AuditService::pick($row, [
            'id', 'meeting_number', 'status', 'content_hash', 'finalized_at', 'content_version',
        ]));
    }
}
