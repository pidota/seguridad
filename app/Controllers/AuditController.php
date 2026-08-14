<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditRepository;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Session;

final class AuditController extends Controller
{
    public function __construct(private readonly AuditRepository $audits = new AuditRepository())
    {
    }

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'module' => trim((string) $request->query('module', '')),
            'action' => trim((string) $request->query('action', '')),
            'q' => trim((string) $request->query('q', '')),
        ];

        $result = $this->audits->paginate($page, 20, $filters);
        $pages = max(1, (int) ceil($result['total'] / 20));

        $this->view('audit/index', [
            'title' => 'Auditoría',
            'user' => Auth::user(),
            'logs' => $result['data'],
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
            'filters' => $filters,
            'modules' => $this->audits->modules(),
            'actions' => \App\Services\AuditService::actionLabels(),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $record = $this->audits->findById((int) $id);

        if ($record === null) {
            Session::flashAlert('error', 'No encontrado', 'El registro de auditoría no existe.');
            $this->redirect(url('/audit'));
        }

        $this->view('audit/show', [
            'title' => 'Detalle de auditoría',
            'user' => Auth::user(),
            'record' => $record,
        ]);
    }
}
