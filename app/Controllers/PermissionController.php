<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PermissionRepository;
use Core\Auth;
use Core\Controller;

final class PermissionController extends Controller
{
    public function index(): void
    {
        $this->view('permissions/index', [
            'title' => 'Permisos',
            'user' => Auth::user(),
            'grouped' => (new PermissionRepository())->groupedByModule(),
        ]);
    }
}
