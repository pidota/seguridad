<?php

declare(strict_types=1);

namespace App\Controllers\Meetings;

use App\Services\Meetings\MeetingAttendanceService;
use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Request;
use Core\Session;

final class MeetingAttendanceController extends Controller
{
    public function __construct(
        private readonly MeetingAttendanceService $attendance = new MeetingAttendanceService()
    ) {
    }

    public function show(Request $request, string $token): void
    {
        try {
            $invitation = $this->attendance->findInvitation($token);
        } catch (HttpException $e) {
            $this->renderError($e->getStatusCode(), $e->getMessage());

            return;
        }

        $participant = $invitation['participant'];
        $meeting = $invitation['meeting'];

        $this->view('meetings/attendance', [
            'title' => 'Confirmación de asistencia',
            'token' => $token,
            'participant' => $participant,
            'meeting' => $meeting,
            'alreadyResponded' => (bool) $invitation['already_responded'],
            'attendanceStatus' => (string) ($participant['attendance_status'] ?? 'pending'),
        ], 'error');
    }

    public function respond(Request $request, string $token): void
    {
        $action = trim((string) $request->input('action', ''));

        try {
            $this->attendance->respond($token, $action);
        } catch (HttpException $e) {
            Session::flashAlert(
                $e->getStatusCode() === 409 ? 'info' : 'error',
                'No se pudo registrar la respuesta',
                $e->getMessage()
            );
            $this->redirect(url('/meetings/attendance/' . $token));

            return;
        }

        $message = $action === 'confirm'
            ? 'Gracias. Su asistencia a la reunión quedó confirmada.'
            : 'Registramos que no asistirá a la reunión.';

        Session::flashAlert('success', 'Respuesta registrada', $message);
        $this->redirect(url('/meetings/attendance/' . $token));
    }

    private function renderError(int $status, string $message): void
    {
        http_response_code($status);
        $view = match ($status) {
            403 => 'errors/403',
            404 => 'errors/404',
            default => 'errors/404',
        };

        echo \Core\View::make($view, [
            'title' => $status . ' — ' . config('app.name'),
            'message' => $message,
            'status' => $status,
        ], 'error');
    }
}
