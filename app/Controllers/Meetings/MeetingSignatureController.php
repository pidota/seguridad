<?php

declare(strict_types=1);

namespace App\Controllers\Meetings;

use App\Services\Meetings\MeetingService;
use App\Services\Meetings\MeetingSignatureService;
use App\Services\Meetings\MeetingSourceModule;
use App\Services\Meetings\UserSignatureService;
use Core\Auth;
use Core\Request;
use Core\Session;

final class MeetingSignatureController extends MeetingController
{
    public function __construct(
        private readonly MeetingSignatureService $signatures = new MeetingSignatureService(),
        private readonly MeetingService $meetings = new MeetingService(),
        private readonly UserSignatureService $userSignatures = new UserSignatureService()
    ) {
    }

    public function pending(Request $request): void
    {
        $this->meetingView('pending-signatures', [
            'title' => 'Firmas pendientes — Reuniones',
            'meetings' => $this->signatures->pendingMeetingsForUser(),
            'pendingCount' => $this->signatures->getPendingCountForUser(),
        ]);
    }

    public function review(Request $request, string $id): void
    {
        $meetingId = (int) $id;

        try {
            $meeting = $this->meetings->findDetailed($meetingId);
            $this->meetings->assertCanView($meeting);
            if (!$this->signatures->canUserSign($meetingId)) {
                throw new \Core\Exceptions\HttpException(403, 'No tiene una solicitud de firma pendiente para esta reunión.');
            }
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/meetings/pending-signatures'));
        }

        $source = (string) ($meeting['source_module'] ?? MeetingSourceModule::ADMIN);
        $userId = Auth::id();
        $activeSignature = $userId !== null ? $this->userSignatures->findActive($userId) : null;

        $this->meetingView('sign', [
            'title' => 'Revisar y firmar — ' . (string) ($meeting['meeting_number'] ?? 'Reunión'),
            'meeting' => $meeting,
            'sourceModule' => $source,
            'activeSignature' => $activeSignature,
            'signAction' => $this->signActionUrl($source, $meetingId),
            'correctionAction' => $this->correctionActionUrl($source, $meetingId),
            'showUrl' => $this->showUrl($source, $meetingId),
            'signatureImageUrl' => $activeSignature !== null
                ? url('/meetings/profile/signature/image')
                : url('/meetings/profile/signature'),
        ]);
    }

    public function sign(Request $request, string $id): void
    {
        $meetingId = (int) $id;

        try {
            $meeting = $this->meetings->findDetailed($meetingId);
            $source = (string) ($meeting['source_module'] ?? MeetingSourceModule::ADMIN);
            $this->signatures->sign($meetingId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, $this->signReviewUrl($source ?? MeetingSourceModule::ADMIN, $meetingId));
        }

        Session::flashAlert('success', 'Firma registrada', 'Su firma simple quedó asociada al registro de reunión.');
        $this->redirect($this->showUrl($source, $meetingId));
    }

    public function requestCorrection(Request $request, string $id): void
    {
        $meetingId = (int) $id;
        $reason = trim((string) $request->input('reason', ''));

        try {
            $meeting = $this->meetings->findDetailed($meetingId);
            $source = (string) ($meeting['source_module'] ?? MeetingSourceModule::ADMIN);
            $this->signatures->requestCorrection($meetingId, $reason);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, $this->signReviewUrl($source ?? MeetingSourceModule::ADMIN, $meetingId));
        }

        Session::flashAlert('success', 'Corrección solicitada', 'Se notificó al responsable del registro para revisar el contenido.');
        $this->redirect(url('/meetings/pending-signatures'));
    }

    public function snapshotImage(Request $request, string $signatureId): void
    {
        try {
            $row = $this->signatures->findSignatureForImage((int) $signatureId);
            $meeting = $this->meetings->findDetailed((int) ($row['meeting_id'] ?? 0));
            $this->meetings->assertCanView($meeting);

            $path = $this->userSignatures->resolveMeetingSignaturePath((string) ($row['signature_snapshot_path'] ?? ''));
            header('Content-Type: image/png');
            header('Content-Length: ' . (string) filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            http_response_code(404);
            exit;
        }
    }

    private function showUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id)
            : url('/meetings/' . $id);
    }

    private function signReviewUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id . '/sign')
            : url('/meetings/' . $id . '/sign');
    }

    private function signActionUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id . '/sign')
            : url('/meetings/' . $id . '/sign');
    }

    private function correctionActionUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id . '/request-correction')
            : url('/meetings/' . $id . '/request-correction');
    }
}
