<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\NotificationService;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Session;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->notifications->paginateForUser($page);

        $this->view('notifications/index', [
            'title' => 'Notificaciones',
            'user' => Auth::user(),
            'notifications' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'unreadCount' => $this->notifications->unreadCount(),
        ]);
    }

    public function markRead(Request $request, string $id): void
    {
        $notificationId = (int) $id;
        if ($notificationId < 1) {
            Session::flashAlert('error', 'Notificación inválida', 'El identificador no es válido.');
            $this->redirect(url('/notifications'));
        }

        $record = $this->notifications->markRead($notificationId);
        if (!$record) {
            Session::flashAlert('warning', 'Sin cambios', 'La notificación ya estaba leída o no existe.');
        }

        $redirect = trim((string) $request->query('redirect', ''));
        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            $this->redirect(url($redirect));
        }

        $this->redirect(url('/notifications'));
    }

    public function markAllRead(): void
    {
        $count = $this->notifications->markAllRead();
        Session::flashAlert(
            'success',
            'Notificaciones actualizadas',
            $count > 0 ? 'Se marcaron ' . $count . ' notificación(es) como leídas.' : 'No había notificaciones pendientes.'
        );
        $this->redirect(url('/notifications'));
    }
}
