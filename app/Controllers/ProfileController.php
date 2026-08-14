<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\Controller;

final class ProfileController extends Controller
{
    public function show(): void
    {
        $this->view('profile/show', [
            'title' => 'Perfil',
            'user' => Auth::user(),
        ]);
    }
}
