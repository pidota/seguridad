<?php

declare(strict_types=1);

namespace App\Controllers\Meetings;

use Core\Auth;
use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Session;

abstract class MeetingController extends Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function meetingView(string $view, array $data = []): void
    {
        $this->view('meetings/' . $view, array_merge([
            'user' => Auth::user(),
            'moduleScripts' => [
                asset('js/modules/meetings/form.js'),
            ],
        ], $data));
    }

    protected function failAndRedirect(\Throwable $e, string $to): never
    {
        if ($e instanceof HttpException && in_array($e->getStatusCode(), [403, 404, 409, 422], true)) {
            Session::flashAlert(
                $e->getStatusCode() === 403 ? 'warning' : 'error',
                'No se pudo completar la acción',
                $e->getMessage()
            );
            $this->redirect($to);
        }

        throw $e;
    }
}
