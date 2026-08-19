<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use App\Repositories\WomenOffice\CaseRepository;
use Core\Auth;

final class WomenFollowUpService
{
    public function __construct(
        private readonly CaseRepository $cases = new CaseRepository(),
        private readonly WomenCaseService $caseService = new WomenCaseService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function agenda(array $filters, int $page, int $perPage = 15): array
    {
        $page = max(1, $page);
        $result = $this->cases->followUpAgenda($filters, $page, $perPage, $this->scopedUserId());
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this->caseService, 'presentListRow'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    private function scopedUserId(): ?int
    {
        if (hasPermission('women.cases.view_all')) {
            return null;
        }

        $userId = Auth::id();

        return $userId !== null ? (int) $userId : null;
    }
}
