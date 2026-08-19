<?php

declare(strict_types=1);

namespace App\Controllers\WomenOffice;

use App\Services\WomenOffice\WomenFollowUpService;
use Core\Request;

final class FollowUpController extends WomenOfficeController
{
    public function __construct(
        private readonly WomenFollowUpService $followUps = new WomenFollowUpService()
    ) {
    }

    public function index(Request $request): void
    {
        $due = trim((string) $request->query('due', 'pending'));
        if (!in_array($due, ['pending', 'today', 'overdue'], true)) {
            $due = 'pending';
        }

        $page = max(1, (int) $request->query('page', 1));
        $result = $this->followUps->agenda(['due' => $due], $page);

        $this->womenView('follow-ups/index', [
            'title' => 'Seguimientos — Oficina de la Mujer',
            'items' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'due' => $due,
        ]);
    }
}
