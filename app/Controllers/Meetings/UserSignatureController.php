<?php

declare(strict_types=1);

namespace App\Controllers\Meetings;

use App\Services\Meetings\UserSignatureService;
use Core\Auth;
use Core\Request;
use Core\Session;

final class UserSignatureController extends MeetingController
{
    public function __construct(
        private readonly UserSignatureService $signatures = new UserSignatureService()
    ) {
    }

    public function show(Request $request): void
    {
        $userId = Auth::id();
        $active = $userId !== null ? $this->signatures->findActive($userId) : null;

        $this->meetingView('signature-profile', [
            'title' => 'Mi firma simple — Perfil',
            'activeSignature' => $active,
            'imageUrl' => $active !== null ? url('/meetings/profile/signature/image') : null,
            'storeUrl' => url('/meetings/profile/signature'),
        ]);
    }

    public function store(Request $request): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            Session::flashAlert('warning', 'Sesión requerida', 'Debe iniciar sesión.');
            $this->redirect(url('/login'));
        }

        try {
            $this->signatures->store($userId, $_FILES['signature'] ?? []);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/meetings/profile/signature'));
        }

        Session::flashAlert('success', 'Firma actualizada', 'Su firma simple quedó registrada para futuras reuniones.');
        $this->redirect(url('/meetings/profile/signature'));
    }

    public function image(Request $request): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            http_response_code(401);
            exit;
        }

        try {
            $active = $this->signatures->findActive($userId);
            if ($active === null) {
                throw new \Core\Exceptions\HttpException(404, 'Firma no encontrada.');
            }

            $path = $this->signatures->resolveAbsolutePath((string) ($active['image_path'] ?? ''));
            header('Content-Type: image/png');
            header('Content-Length: ' . (string) filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            http_response_code(404);
            exit;
        }
    }
}
