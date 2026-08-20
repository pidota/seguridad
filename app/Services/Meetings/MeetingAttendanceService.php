<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Repositories\Meetings\MeetingParticipantRepository;
use App\Services\AuditService;
use App\Services\MailService;
use Core\Exceptions\HttpException;
use Core\Logger;
use Core\Request;
use Core\View;

final class MeetingAttendanceService
{
    public function __construct(
        private readonly MeetingParticipantRepository $participants = new MeetingParticipantRepository(),
        private readonly MeetingService $meetings = new MeetingService(),
        private readonly MailService $mail = new MailService(),
        private readonly MeetingAuditService $audit = new MeetingAuditService()
    ) {
    }

    /**
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendInvitations(int $meetingId): array
    {
        $meeting = $this->meetings->findDetailed($meetingId);
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($meeting['participants'] ?? [] as $participant) {
            if (($participant['participant_type'] ?? '') !== 'external') {
                continue;
            }

            $email = trim((string) ($participant['external_email'] ?? ''));
            if ($email === '') {
                $stats['skipped']++;
                continue;
            }

            $participantId = (int) ($participant['id'] ?? 0);
            if ($participantId < 1) {
                $stats['skipped']++;
                continue;
            }

            $token = $this->participants->ensureAttendanceToken($participantId);
            if ($this->sendInvitationEmail($meeting, $participant, $token, $email)) {
                $this->participants->markAttendanceEmailSent($participantId);
                $stats['sent']++;
                continue;
            }

            $stats['failed']++;
        }

        if ($stats['sent'] > 0) {
            Logger::info(sprintf(
                'Reunión %s: se enviaron %d correo(s) de confirmación de asistencia.',
                (string) ($meeting['meeting_number'] ?? $meetingId),
                $stats['sent']
            ));
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function findInvitation(string $token): array
    {
        $token = trim($token);
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new HttpException(404, 'La invitación no existe o expiró.');
        }

        $participant = $this->participants->findByAttendanceToken($token);
        if ($participant === null) {
            throw new HttpException(404, 'La invitación no existe o expiró.');
        }

        $meeting = $this->meetings->findDetailed((int) ($participant['meeting_id'] ?? 0));
        $status = (string) ($meeting['status'] ?? '');
        if (in_array($status, [MeetingStatus::CANCELLED, MeetingStatus::DRAFT], true)) {
            throw new HttpException(403, 'Esta invitación ya no está disponible.');
        }

        return [
            'token' => $token,
            'participant' => $participant,
            'meeting' => $meeting,
            'already_responded' => ($participant['attendance_status'] ?? 'pending') !== 'pending',
        ];
    }

    public function respond(string $token, string $action): void
    {
        $invitation = $this->findInvitation($token);
        $participant = $invitation['participant'];
        $meeting = $invitation['meeting'];

        if (($participant['attendance_status'] ?? 'pending') !== 'pending') {
            throw new HttpException(409, 'Ya registramos su respuesta anteriormente.');
        }

        $status = match ($action) {
            'confirm' => 'confirmed',
            'decline' => 'declined',
            default => throw new HttpException(422, 'Acción de asistencia inválida.'),
        };

        $participantId = (int) ($participant['id'] ?? 0);
        $meetingId = (int) ($meeting['id'] ?? 0);
        $this->participants->updateAttendanceResponse(
            $participantId,
            $status,
            Request::capture()->ip()
        );

        $payload = [
            'participant_id' => $participantId,
            'external_name' => (string) ($participant['external_name'] ?? ''),
            'attendance_status' => $status,
        ];

        if ($status === 'confirmed') {
            $this->audit->attendanceConfirmed($meetingId, $payload);
        } else {
            $this->audit->attendanceDeclined($meetingId, $payload);
        }
    }

    public function resetForMeeting(int $meetingId): void
    {
        $this->participants->resetAttendanceForMeeting($meetingId);
    }

    /**
     * @param array<string, mixed> $meeting
     * @param array<string, mixed> $participant
     */
    private function sendInvitationEmail(array $meeting, array $participant, string $token, string $email): bool
    {
        $confirmUrl = url('/meetings/attendance/' . $token);
        $meetingNumber = (string) ($meeting['meeting_number'] ?? '');
        $subject = 'Confirmación de asistencia — Reunión ' . $meetingNumber;

        $html = View::make('emails/meetings/attendance_confirmation', [
            'participantName' => (string) ($participant['external_name'] ?? ''),
            'meetingNumber' => $meetingNumber,
            'meetingDate' => !empty($meeting['meeting_date'])
                ? date('d-m-Y', strtotime((string) $meeting['meeting_date']))
                : '—',
            'meetingTime' => !empty($meeting['meeting_time'])
                ? substr((string) $meeting['meeting_time'], 0, 5)
                : '—',
            'meetingPlace' => (string) ($meeting['meeting_place'] ?? '—'),
            'confirmUrl' => $confirmUrl,
            'appName' => (string) config('app.name'),
        ], null);

        $text = implode("\n", [
            'Estimado/a ' . (string) ($participant['external_name'] ?? '') . ',',
            '',
            'Se registró su participación en la reunión ' . $meetingNumber . '.',
            'Fecha: ' . (!empty($meeting['meeting_date']) ? date('d-m-Y', strtotime((string) $meeting['meeting_date'])) : '—'),
            'Hora: ' . (!empty($meeting['meeting_time']) ? substr((string) $meeting['meeting_time'], 0, 5) : '—'),
            'Lugar: ' . (string) ($meeting['meeting_place'] ?? '—'),
            '',
            'Confirme o decline su asistencia en el siguiente enlace:',
            $confirmUrl,
        ]);

        return $this->mail->sendHtml($email, $subject, $html, $text);
    }
}
