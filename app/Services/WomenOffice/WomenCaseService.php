<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use App\Repositories\WomenOffice\AggressorRepository;
use App\Repositories\WomenOffice\CatalogRepository;
use App\Repositories\WomenOffice\CaseActionRepository;
use App\Repositories\WomenOffice\CaseFollowUpRepository;
use App\Repositories\WomenOffice\CaseNeedRepository;
use App\Repositories\WomenOffice\CaseReferralRepository;
use App\Repositories\WomenOffice\CaseRepository;
use App\Repositories\WomenOffice\CaseRiskFactorRepository;
use App\Repositories\WomenOffice\CaseViolenceRepository;
use App\Repositories\WomenOffice\FormalReportRepository;
use App\Repositories\WomenOffice\LinkedMinorRepository;
use App\Repositories\WomenOffice\PersonRepository;
use App\Repositories\WomenOffice\PreviousReportRepository;
use App\Repositories\WomenOffice\ProtectiveMeasureRepository;
use App\Services\AuditService;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;

final class WomenCaseService
{
    public function __construct(
        private readonly CaseRepository $cases = new CaseRepository(),
        private readonly CaseViolenceRepository $violence = new CaseViolenceRepository(),
        private readonly CaseRiskFactorRepository $riskFactors = new CaseRiskFactorRepository(),
        private readonly ProtectiveMeasureRepository $protectiveMeasures = new ProtectiveMeasureRepository(),
        private readonly CaseNeedRepository $needs = new CaseNeedRepository(),
        private readonly LinkedMinorRepository $linkedMinors = new LinkedMinorRepository(),
        private readonly CaseActionRepository $actions = new CaseActionRepository(),
        private readonly CaseReferralRepository $referrals = new CaseReferralRepository(),
        private readonly CaseFollowUpRepository $followUps = new CaseFollowUpRepository(),
        private readonly AggressorRepository $aggressors = new AggressorRepository(),
        private readonly PreviousReportRepository $previousReports = new PreviousReportRepository(),
        private readonly FormalReportRepository $formalReports = new FormalReportRepository(),
        private readonly PersonRepository $people = new PersonRepository(),
        private readonly CatalogRepository $catalogs = new CatalogRepository(),
        private readonly WomenCaseNumberService $numbers = new WomenCaseNumberService(),
        private readonly WomenCaseAccessPolicy $access = new WomenCaseAccessPolicy(),
        private readonly WomenAuditService $womenAudit = new WomenAuditService()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->search([], 1, 200)['data'];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function search(array $filters, int $page, int $perPage = 15): array
    {
        $page = max(1, $page);
        $scoped = $this->scopedFilters($filters);
        $result = $this->cases->paginate($scoped, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'presentListRow'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    public function staffOptions(): array
    {
        return $this->cases->staffOptions();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function ageRangeOptions(): array
    {
        return [
            ['value' => 'under_18', 'label' => 'Menor de 18'],
            ['value' => '18_29', 'label' => '18–29'],
            ['value' => '30_39', 'label' => '30–39'],
            ['value' => '40_49', 'label' => '40–49'],
            ['value' => '50_59', 'label' => '50–59'],
            ['value' => '60_plus', 'label' => '60 o más'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function priorityOptions(): array
    {
        return [
            ['value' => 'low', 'label' => 'Baja'],
            ['value' => 'medium', 'label' => 'Media'],
            ['value' => 'high', 'label' => 'Alta'],
            ['value' => 'urgent', 'label' => 'Urgente'],
        ];
    }

    public static function ageRangeLabel(?int $age): string
    {
        if ($age === null) {
            return '—';
        }

        if ($age < 18) {
            return 'Menor de 18';
        }

        if ($age <= 29) {
            return '18–29';
        }

        if ($age <= 39) {
            return '30–39';
        }

        if ($age <= 49) {
            return '40–49';
        }

        if ($age <= 59) {
            return '50–59';
        }

        return '60 o más';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function presentListRow(array $row): array
    {
        $presented = $this->present($row);
        $age = PersonService::age(isset($row['birth_date']) ? (string) $row['birth_date'] : null);
        $showFullName = hasPermission('women.cases.view_all');
        $nextDate = trim((string) ($row['next_follow_up_date'] ?? ''));

        $presented['person_display_name'] = $showFullName
            ? (string) ($presented['person_full_name'] ?? '—')
            : (string) ($presented['person_initials'] ?? '—');
        $presented['age_range_label'] = self::ageRangeLabel($age);
        $presented['person_sector_name'] = trim((string) ($row['person_sector_name'] ?? '')) !== ''
            ? (string) $row['person_sector_name']
            : '—';
        $presented['violence_types_label'] = trim((string) ($row['violence_types_label'] ?? '')) !== ''
            ? (string) $row['violence_types_label']
            : '—';
        $presented['next_follow_up_date'] = $nextDate !== '' ? $nextDate : null;
        $presented['next_follow_up_label'] = $nextDate !== ''
            ? date('d-m-Y', strtotime($nextDate))
            : '—';
        $presented['next_follow_up_tone'] = $nextDate !== '' ? $this->followUpTone($nextDate) : '';

        return $presented;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function scopedFilters(array $filters): array
    {
        if (!hasPermission('women.cases.view_all')) {
            $userId = Auth::id();
            if ($userId !== null) {
                $filters['scoped_user_id'] = $userId;
            }
            unset($filters['created_by']);
        }

        return $filters;
    }

    private function followUpTone(string $date): string
    {
        $today = date('Y-m-d');

        if ($date < $today) {
            return 'overdue';
        }

        if ($date === $today) {
            return 'due';
        }

        return 'scheduled';
    }

    public function find(int $id): array
    {
        $row = $this->cases->findById($id);

        if ($row === null) {
            throw new HttpException(404, 'El caso no existe.');
        }

        return $this->present($row);
    }

    public function findDetailed(int $id): array
    {
        $case = $this->find($id);
        $case['violence_types'] = $this->violence->forCase($id);
        $case['violence_type_ids'] = array_map(
            static fn (array $item): int => (int) $item['violence_type_id'],
            $case['violence_types']
        );
        $case['has_facts'] = $this->hasFacts($case);
        $case['aggressor'] = $this->presentAggressor($this->aggressors->findByCaseId($id));
        $case['has_aggressor'] = $this->hasAggressor($case);
        $case['previous_reports'] = $this->previousReports->forCase($id);
        $case['formal_report'] = $this->formalReports->findByCaseId($id);
        $case['has_background'] = $this->hasBackground($case);
        $case['risk_factors'] = $this->riskFactors->forCase($id);
        $case['risk_factor_ids'] = array_map(
            static fn (array $item): int => (int) $item['risk_factor_id'],
            $case['risk_factors']
        );
        $case['has_risk_assessment'] = $this->hasRiskAssessment($case);
        $case['protective_measures'] = $this->protectiveMeasures->forCase($id);
        $case['needs'] = $this->needs->forCase($id);
        $case['need_ids'] = array_map(
            static fn (array $item): int => (int) $item['need_id'],
            $case['needs']
        );
        $case['linked_minors'] = $this->presentLinkedMinors($this->linkedMinors->forCase($id));
        $case['has_support_context'] = $this->hasSupportContext($case);
        $case['actions'] = $this->presentActions($this->actions->forCase($id));
        $case['has_actions'] = ($case['actions'] ?? []) !== [];
        $case['referrals'] = $this->referrals->forCase($id);
        $case['has_referrals'] = ($case['referrals'] ?? []) !== [];
        $case['followups'] = $this->presentFollowUps($this->followUps->forCase($id));
        $case['has_followups'] = ($case['followups'] ?? []) !== [];

        return $case;
    }

    public function assertCanView(array $case): void
    {
        $this->access->assertCanView($case);
    }

    public function assertCanEdit(array $case): void
    {
        $this->access->assertCanEdit($case);
    }

    public function assertCanClose(array $case): void
    {
        $this->access->assertCanClose($case);
    }

    public function assertCanCancel(array $case): void
    {
        $this->access->assertCanCancel($case);
    }

    public function isTerminal(array $case): bool
    {
        return $this->access->isTerminal($case);
    }

    public function updateFacts(int $id, array $data): void
    {
        $case = $this->findDetailed($id);
        $this->assertCanEdit($case);

        $payload = $this->factsPayload($data);
        $violenceItems = $this->violenceItems($data);
        $before = $this->auditSnapshot($case) + [
            'violence_types' => $case['violence_types'],
        ];

        $pdo = Database::connection();
        $started = $pdo->inTransaction();

        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->cases->updateIncidentFacts($id, $payload);
            $this->violence->sync($id, $violenceItems);

            $this->syncOperationalStatus($id);
            $afterCase = $this->findDetailed($id);
            $after = $this->auditSnapshot($afterCase) + [
                'violence_types' => $afterCase['violence_types'],
            ];

$this->womenAudit->caseUpdated(
                $id,
                $before,
                $after
            );

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateAggressor(int $id, array $data): void
    {
        $case = $this->findDetailed($id);
        $this->assertCanEdit($case);

        $relationship = $this->relationshipPayload($data);
        $aggressor = $this->aggressorPayload($data);
        $before = $this->auditSnapshot($case) + [
            'aggressor' => $case['aggressor'],
        ];

        $pdo = Database::connection();
        $started = $pdo->inTransaction();

        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->cases->updateRelationship($id, $relationship);

            if ($this->isAggressorEmpty($aggressor)) {
                $this->aggressors->deleteForCase($id);
            } else {
                $this->aggressors->upsert($id, $aggressor);
            }

            $this->syncOperationalStatus($id);
            $afterCase = $this->findDetailed($id);
            $after = $this->auditSnapshot($afterCase) + [
                'aggressor' => $afterCase['aggressor'],
            ];

$this->womenAudit->caseUpdated(
                $id,
                $before,
                $after
            );

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateBackground(int $id, array $data): void
    {
        $case = $this->findDetailed($id);
        $this->assertCanEdit($case);

        $background = $this->backgroundPayload($data);
        $previousItems = $this->previousReportItems($data);
        $formalReport = $this->formalReportPayload($data);

        $before = $this->auditSnapshot($case) + [
            'previous_reports' => $case['previous_reports'],
            'formal_report' => $case['formal_report'],
        ];

        $pdo = Database::connection();
        $started = $pdo->inTransaction();

        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->cases->updateBackground($id, $background);

            if (($background['has_previous_reports'] ?? '') === 'yes') {
                $this->previousReports->sync($id, $previousItems);
            } else {
                $this->previousReports->sync($id, []);
            }

            if (($background['has_formal_current_report'] ?? '') === 'yes') {
                $this->formalReports->upsert($id, $formalReport);
            } else {
                $this->formalReports->deleteForCase($id);
            }

            $this->syncOperationalStatus($id);
            $afterCase = $this->findDetailed($id);
            $after = $this->auditSnapshot($afterCase) + [
                'previous_reports' => $afterCase['previous_reports'],
                'formal_report' => $afterCase['formal_report'],
            ];

$this->womenAudit->caseUpdated(
                $id,
                $before,
                $after
            );

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateRiskPriority(int $id, array $data): void
    {
        $case = $this->findDetailed($id);
        $this->assertCanEdit($case);

        $priority = $this->priorityPayload($data, $case);
        $riskItems = $this->riskFactorItems($data);

        $before = $this->auditSnapshot($case) + [
            'risk_factors' => $case['risk_factors'],
        ];

        $pdo = Database::connection();
        $started = $pdo->inTransaction();

        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->cases->updateRiskPriority($id, $priority);
            $this->riskFactors->sync($id, $riskItems);

            $this->syncOperationalStatus($id);
            $afterCase = $this->findDetailed($id);
            $after = $this->auditSnapshot($afterCase) + [
                'risk_factors' => $afterCase['risk_factors'],
            ];

$this->womenAudit->caseUpdated(
                $id,
                $before,
                $after
            );

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateSupport(int $id, array $data): void
    {
        $case = $this->findDetailed($id);
        $this->assertCanEdit($case);

        $support = $this->supportPayload($data);
        $measureItems = $this->protectiveMeasureItems($data);
        $needItems = $this->needItems($data);
        $minorItems = $this->linkedMinorItems($data);

        $before = $this->auditSnapshot($case) + [
            'protective_measures' => $case['protective_measures'],
            'needs' => $case['needs'],
            'linked_minors' => $case['linked_minors'],
        ];

        $pdo = Database::connection();
        $started = $pdo->inTransaction();

        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->cases->updateSupport($id, $support);

            if (($support['has_protective_measures'] ?? '') === 'yes') {
                $this->protectiveMeasures->sync($id, $measureItems);
            } else {
                $this->protectiveMeasures->sync($id, []);
            }

            $this->needs->sync($id, $needItems);

            if (($support['has_linked_minors'] ?? '') === 'yes') {
                $this->linkedMinors->sync($id, $minorItems);
            } else {
                $this->linkedMinors->sync($id, []);
            }

            $this->syncOperationalStatus($id);
            $afterCase = $this->findDetailed($id);
            $after = $this->auditSnapshot($afterCase) + [
                'protective_measures' => $afterCase['protective_measures'],
                'needs' => $afterCase['needs'],
                'linked_minors' => $afterCase['linked_minors'],
            ];

$this->womenAudit->caseUpdated(
                $id,
                $before,
                $after
            );

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateActions(int $id, array $data): void
    {
        $case = $this->findDetailed($id);
        $this->assertCanEdit($case);

        $actionItems = $this->actionItems($data);
        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $before = $this->auditSnapshot($case) + [
            'actions' => $case['actions'],
        ];

        $pdo = Database::connection();
        $started = $pdo->inTransaction();

        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->actions->sync($id, $actionItems, $userId);

            $this->syncOperationalStatus($id);
            $afterCase = $this->findDetailed($id);
            $after = $this->auditSnapshot($afterCase) + [
                'actions' => $afterCase['actions'],
            ];

$this->womenAudit->caseUpdated(
                $id,
                $before,
                $after
            );

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateReferrals(int $id, array $data): void
    {
        $case = $this->findDetailed($id);
        $this->assertCanEdit($case);

        $referralItems = $this->referralItems($data);
        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $before = $this->auditSnapshot($case) + [
            'referrals' => $case['referrals'],
        ];

        $pdo = Database::connection();
        $started = $pdo->inTransaction();

        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->referrals->sync($id, $referralItems, $userId);

            $this->syncOperationalStatus($id);
            $afterCase = $this->findDetailed($id);
            $after = $this->auditSnapshot($afterCase) + [
                'referrals' => $afterCase['referrals'],
            ];

$this->womenAudit->caseUpdated(
                $id,
                $before,
                $after
            );

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateFollowUps(int $id, array $data): void
    {
        $case = $this->findDetailed($id);
        $this->assertCanEdit($case);

        $followUpItems = $this->followUpItems($data);
        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $before = $this->auditSnapshot($case) + [
            'followups' => $case['followups'],
        ];

        $pdo = Database::connection();
        $started = $pdo->inTransaction();

        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->followUps->sync($id, $followUpItems, $userId);

            $this->syncOperationalStatus($id);
            $afterCase = $this->findDetailed($id);
            $after = $this->auditSnapshot($afterCase) + [
                'followups' => $afterCase['followups'],
            ];

$this->womenAudit->caseUpdated(
                $id,
                $before,
                $after
            );

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function closeCase(int $id, array $data): void
    {
        $case = $this->findDetailed($id);
        $this->assertCanClose($case);
        $before = $this->auditSnapshot($case);

        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $notes = trim((string) ($data['closure_notes'] ?? ''));

        $this->cases->closeCase($id, [
            'status_id' => $this->catalogs->statusId(CaseStatus::CLOSED),
            'closed_at' => date('Y-m-d H:i:s'),
            'closed_by' => $userId,
            'closure_notes' => $notes !== '' ? $notes : null,
        ]);

        $afterCase = $this->findDetailed($id);
        $this->womenAudit->caseUpdated(
            $id,
            $before,
            $this->auditSnapshot($afterCase) + [
                'case_status_slug' => CaseStatus::CLOSED,
                'closed_at' => $afterCase['closed_at'] ?? null,
                'closure_notes' => $notes !== '' ? $notes : null,
            ]
        );
    }

    public function cancelCase(int $id, array $data): void
    {
        $case = $this->findDetailed($id);
        $this->assertCanCancel($case);
        $before = $this->auditSnapshot($case);

        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $reason = trim((string) ($data['cancellation_reason'] ?? ''));

        $this->cases->cancelCase($id, [
            'status_id' => $this->catalogs->statusId(CaseStatus::CANCELLED),
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancelled_by' => $userId,
            'cancellation_reason' => $reason,
        ]);

        $afterCase = $this->findDetailed($id);
        $this->womenAudit->caseUpdated(
            $id,
            $before,
            $this->auditSnapshot($afterCase) + [
                'case_status_slug' => CaseStatus::CANCELLED,
                'cancelled_at' => $afterCase['cancelled_at'] ?? null,
                'cancellation_reason' => $reason,
            ]
        );
    }

    public function createRegistration(int $personId, array $data): int
    {
        if ($this->people->findById($personId) === null) {
            throw new HttpException(422, 'Debe seleccionar una persona afectada válida.');
        }

        $reportChannelId = (int) ($data['report_channel_id'] ?? 0);
        $channelSlug = $this->catalogs->reportChannelSlug($reportChannelId);

        if ($channelSlug === null) {
            throw new HttpException(422, 'Seleccione un canal de ingreso válido.');
        }

        $reportChannelOther = trim((string) ($data['report_channel_other'] ?? ''));
        if ($channelSlug === 'otro' && $reportChannelOther === '') {
            throw new HttpException(422, 'Especifique el canal de ingreso.');
        }

        if ($channelSlug !== 'otro') {
            $reportChannelOther = '';
        }

        $reportedAt = $this->reportedAt($data);
        $createdBy = Auth::id();

        if ($createdBy === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $pdo = Database::connection();
        $started = $pdo->inTransaction();

        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $caseNumber = $this->numbers->next();
            $statusId = $this->catalogs->statusId(CaseStatus::REGISTERED);

            $id = $this->cases->create([
                'case_number' => $caseNumber,
                'reported_at' => $reportedAt,
                'report_channel_id' => $reportChannelId,
                'report_channel_other' => $reportChannelOther !== '' ? $reportChannelOther : null,
                'incident_date_precision' => 'undetermined',
                'affected_person_id' => $personId,
                'case_status_id' => $statusId,
                'created_by' => $createdBy,
            ]);

            $created = $this->cases->findById($id);
            $this->womenAudit->caseCreated(
                $id,
                $this->auditSnapshot($created ?? [])
            );

            if (!$started) {
                $pdo->commit();
            }

            PersonContext::forget();

            return $id;
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $row['person_full_name'] = PersonService::fullName($row);
        $row['person_age'] = PersonService::age(isset($row['birth_date']) ? (string) $row['birth_date'] : null);
        $row['person_initials'] = $this->initials($row);
        $row['incident_date_precision_label'] = self::datePrecisionLabel((string) ($row['incident_date_precision'] ?? ''));
        $row['current_relationship_label'] = self::triStateLabel(
            isset($row['current_relationship']) ? (string) $row['current_relationship'] : null
        );
        $row['is_first_occurrence_label'] = self::triStateLabel(
            isset($row['is_first_occurrence']) ? (string) $row['is_first_occurrence'] : null
        );
        $row['has_previous_reports_label'] = self::triStateLabel(
            isset($row['has_previous_reports']) ? (string) $row['has_previous_reports'] : null
        );
        $row['has_formal_current_report_label'] = self::triStateLabel(
            isset($row['has_formal_current_report']) ? (string) $row['has_formal_current_report'] : null
        );
        $row['has_protective_measures_label'] = self::triStateLabel(
            isset($row['has_protective_measures']) ? (string) $row['has_protective_measures'] : null
        );
        $row['has_linked_minors_label'] = self::triStateLabel(
            isset($row['has_linked_minors']) ? (string) $row['has_linked_minors'] : null
        );
        $row['has_dependents_label'] = self::binaryLabel(
            isset($row['has_dependents']) ? (string) $row['has_dependents'] : null
        );
        $row['priority_label'] = self::priorityLabel(
            isset($row['priority']) ? (string) $row['priority'] : null
        );

        return $row;
    }

    public static function priorityLabel(?string $priority): string
    {
        return match ($priority) {
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
            default => '—',
        };
    }

    public static function triStateLabel(?string $value): string
    {
        return match ($value) {
            'yes' => 'Sí',
            'no' => 'No',
            'unknown' => 'No informado',
            default => '—',
        };
    }

    public static function binaryLabel(?string $value): string
    {
        return match ($value) {
            'yes' => 'Sí',
            'no' => 'No',
            default => '—',
        };
    }

    public static function genderLabel(?string $value): string
    {
        return match ($value) {
            'female' => 'Femenino',
            'male' => 'Masculino',
            'other' => 'Otro',
            'unknown' => 'No informado',
            default => '—',
        };
    }

    public static function currentRelationshipLabel(?string $value): string
    {
        return self::triStateLabel($value);
    }

    public static function datePrecisionLabel(string $precision): string
    {
        return match ($precision) {
            'exact' => 'Fecha exacta',
            'approximate' => 'Fecha aproximada',
            'undetermined' => 'No determinada',
            default => '—',
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function reportedAt(array $data): string
    {
        $date = trim((string) ($data['reported_date'] ?? date('Y-m-d')));
        $time = trim((string) ($data['reported_time'] ?? date('H:i')));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new HttpException(422, 'La fecha de registro no es válida.');
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        } elseif (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) !== 1) {
            throw new HttpException(422, 'La hora de registro no es válida.');
        }

        return $date . ' ' . $time;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function factsPayload(array $data): array
    {
        $precision = trim((string) ($data['incident_date_precision'] ?? 'undetermined'));
        $incidentDate = trim((string) ($data['incident_date'] ?? ''));
        $incidentTime = trim((string) ($data['incident_time'] ?? ''));
        $timeNotes = $this->nullable($data['incident_time_notes'] ?? null);

        if ($precision === 'undetermined') {
            $incidentDate = null;
            $incidentTime = null;
        } else {
            $incidentDate = $incidentDate !== '' ? $incidentDate : null;
            if ($incidentTime !== '') {
                if (preg_match('/^\d{2}:\d{2}$/', $incidentTime) === 1) {
                    $incidentTime .= ':00';
                }
            } else {
                $incidentTime = null;
            }
        }

        return [
            'incident_date_precision' => $precision,
            'incident_date' => $incidentDate,
            'incident_time' => $incidentTime,
            'incident_time_notes' => $timeNotes,
            'incident_place' => $this->nullable($data['incident_place'] ?? null),
            'incident_sector_id' => $this->nullableInt($data['incident_sector_id'] ?? null),
            'incident_address' => $this->nullable($data['incident_address'] ?? null),
            'description' => trim((string) ($data['description'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{violence_type_id: int, other_text: ?string}>
     */
    private function violenceItems(array $data): array
    {
        $raw = $data['violence_type_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $others = is_array($data['violence_other'] ?? null) ? $data['violence_other'] : [];
        $items = [];

        foreach (array_unique(array_map('intval', $raw)) as $typeId) {
            if ($typeId < 1) {
                continue;
            }

            $slug = $this->catalogs->violenceTypeSlug($typeId);
            $otherText = null;

            if ($slug === 'otra') {
                $otherText = trim((string) ($others[$typeId] ?? $others[(string) $typeId] ?? ''));
                $otherText = $otherText !== '' ? $otherText : null;
            }

            $items[] = [
                'violence_type_id' => $typeId,
                'other_text' => $otherText,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>|null
     */
    private function presentAggressor(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $row['full_name'] = self::aggressorFullName($row);
        $row['age'] = PersonService::age(isset($row['birth_date']) ? (string) $row['birth_date'] : null);

        return $row;
    }

    public static function aggressorFullName(array $row): string
    {
        return trim(implode(' ', array_filter([
            trim((string) ($row['first_names'] ?? '')),
            trim((string) ($row['paternal_surname'] ?? '')),
            trim((string) ($row['maternal_surname'] ?? '')),
        ], static fn (string $part): bool => $part !== '')));
    }

    /**
     * @param array<string, mixed> $case
     */
    private function hasAggressor(array $case): bool
    {
        if ((int) ($case['relationship_type_id'] ?? 0) > 0) {
            return true;
        }

        if (trim((string) ($case['current_relationship'] ?? '')) !== '') {
            return true;
        }

        $aggressor = $case['aggressor'] ?? null;
        if (!is_array($aggressor)) {
            return false;
        }

        return !$this->isAggressorEmpty($aggressor);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function relationshipPayload(array $data): array
    {
        $typeId = $this->nullableInt($data['relationship_type_id'] ?? null);
        $other = $this->nullable($data['relationship_other'] ?? null);
        $slug = $typeId !== null ? $this->catalogs->relationshipTypeSlug($typeId) : null;

        if ($slug !== 'otro') {
            $other = null;
        }

        $current = trim((string) ($data['current_relationship'] ?? ''));

        return [
            'relationship_type_id' => $typeId,
            'relationship_other' => $other,
            'current_relationship' => in_array($current, ['yes', 'no', 'unknown'], true) ? $current : null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function aggressorPayload(array $data): array
    {
        $rut = trim((string) ($data['aggressor_rut'] ?? ''));
        $normalized = null;
        $formatted = null;

        if ($rut !== '') {
            $normalized = \App\Support\ChileanRutValidator::normalize($rut);
            if ($normalized === null) {
                throw new HttpException(422, 'El RUT de la persona denunciada no es válido.');
            }

            $formatted = \App\Support\ChileanRutValidator::format($normalized) ?? $normalized;
        }

        $birthDate = trim((string) ($data['aggressor_birth_date'] ?? ''));
        $approxAge = $this->nullable($data['aggressor_approximate_age'] ?? null);

        if ($birthDate !== '') {
            $approxAge = null;
        } elseif ($approxAge !== null) {
            $birthDate = null;
        } else {
            $birthDate = null;
        }

        return [
            'first_names' => $this->nullable($data['aggressor_first_names'] ?? null),
            'paternal_surname' => $this->nullable($data['aggressor_paternal_surname'] ?? null),
            'maternal_surname' => $this->nullable($data['aggressor_maternal_surname'] ?? null),
            'rut' => $formatted,
            'rut_normalized' => $normalized,
            'birth_date' => $birthDate !== '' ? $birthDate : null,
            'approximate_age' => $approxAge,
            'phone' => $this->nullable($data['aggressor_phone'] ?? null),
            'address' => $this->nullable($data['aggressor_address'] ?? null),
            'occupation' => $this->nullable($data['aggressor_occupation'] ?? null),
            'notes' => $this->nullable($data['aggressor_notes'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isAggressorEmpty(array $payload): bool
    {
        foreach ([
            'first_names', 'paternal_surname', 'maternal_surname', 'rut', 'rut_normalized',
            'birth_date', 'approximate_age', 'phone', 'address', 'occupation', 'notes',
        ] as $field) {
            if (trim((string) ($payload[$field] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function backgroundPayload(array $data): array
    {
        $first = $this->triState($data['is_first_occurrence'] ?? null);
        $previous = $this->triState($data['has_previous_reports'] ?? null);
        $formal = $this->triState($data['has_formal_current_report'] ?? null);

        $frequency = $this->nullable($data['occurrence_frequency'] ?? null);
        $since = $this->nullable($data['occurring_since'] ?? null);
        $occurrenceNotes = $this->nullable($data['occurrence_notes'] ?? null);

        if ($first !== 'no') {
            $frequency = null;
            $since = null;
            $occurrenceNotes = null;
        }

        return [
            'is_first_occurrence' => $first,
            'occurrence_frequency' => $frequency,
            'occurring_since' => $since,
            'occurrence_notes' => $occurrenceNotes,
            'has_previous_reports' => $previous,
            'has_formal_current_report' => $formal,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{institution_name: string, report_date: ?string, reference_number: ?string, notes: ?string}>
     */
    private function previousReportItems(array $data): array
    {
        $raw = $data['previous_reports'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $institution = trim((string) ($row['institution_name'] ?? ''));
            if ($institution === '') {
                continue;
            }

            $date = trim((string) ($row['report_date'] ?? ''));

            $items[] = [
                'institution_name' => $institution,
                'report_date' => $date !== '' ? $date : null,
                'reference_number' => $this->nullable($row['reference_number'] ?? null),
                'notes' => $this->nullable($row['notes'] ?? null),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function formalReportPayload(array $data): array
    {
        $institutionId = $this->nullableInt($data['formal_report_institution_id'] ?? null);
        $other = $this->nullable($data['formal_report_institution_other'] ?? null);
        $slug = $institutionId !== null ? $this->catalogs->formalReportInstitutionSlug($institutionId) : null;

        if ($slug !== 'otra') {
            $other = null;
        }

        $date = trim((string) ($data['formal_report_date'] ?? ''));

        return [
            'institution_id' => $institutionId,
            'institution_other' => $other,
            'reference_number' => $this->nullable($data['formal_report_reference_number'] ?? null),
            'report_date' => $date !== '' ? $date : null,
            'notes' => $this->nullable($data['formal_report_notes'] ?? null),
        ];
    }

    private function triState(mixed $value): ?string
    {
        $value = trim((string) $value);

        return in_array($value, ['yes', 'no', 'unknown'], true) ? $value : null;
    }

    /**
     * @param array<string, mixed> $case
     */
    private function hasBackground(array $case): bool
    {
        foreach (['is_first_occurrence', 'has_previous_reports', 'has_formal_current_report'] as $field) {
            if (trim((string) ($case[$field] ?? '')) !== '') {
                return true;
            }
        }

        if (($case['previous_reports'] ?? []) !== []) {
            return true;
        }

        return is_array($case['formal_report'] ?? null);
    }

    /**
     * @param array<string, mixed> $case
     */
    private function hasRiskAssessment(array $case): bool
    {
        if (trim((string) ($case['priority'] ?? '')) !== '') {
            return true;
        }

        return ($case['risk_factors'] ?? []) !== [];
    }

    /**
     * @param array<string, mixed> $case
     */
    private function hasSupportContext(array $case): bool
    {
        foreach (['has_protective_measures', 'has_linked_minors', 'has_dependents'] as $field) {
            if (trim((string) ($case[$field] ?? '')) !== '') {
                return true;
            }
        }

        if (($case['protective_measures'] ?? []) !== []) {
            return true;
        }

        if (($case['needs'] ?? []) !== []) {
            return true;
        }

        if (($case['linked_minors'] ?? []) !== []) {
            return true;
        }

        return trim((string) ($case['dependents_notes'] ?? '')) !== ''
            || (int) ($case['dependents_count'] ?? 0) > 0;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function presentLinkedMinors(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['gender_label'] = self::genderLabel(
                isset($row['gender']) ? (string) $row['gender'] : null
            );
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function supportPayload(array $data): array
    {
        $hasProtective = $this->triState($data['has_protective_measures'] ?? null);
        $hasMinors = $this->triState($data['has_linked_minors'] ?? null);
        $hasDependents = $this->binaryState($data['has_dependents'] ?? null);

        $count = $this->nullableInt($data['dependents_count'] ?? null);
        $dependentsNotes = $this->nullable($data['dependents_notes'] ?? null);

        if ($hasDependents !== 'yes') {
            $count = null;
            $dependentsNotes = null;
        }

        return [
            'has_protective_measures' => $hasProtective,
            'has_linked_minors' => $hasMinors,
            'has_dependents' => $hasDependents,
            'dependents_count' => $count,
            'dependents_notes' => $dependentsNotes,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{
     *     measure_type_id: ?int,
     *     institution: ?string,
     *     start_date: ?string,
     *     end_date: ?string,
     *     cause_number: ?string,
     *     notes: ?string
     * }>
     */
    private function protectiveMeasureItems(array $data): array
    {
        $raw = $data['protective_measures'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $typeId = $this->nullableInt($row['measure_type_id'] ?? null);
            $institution = $this->nullable($row['institution'] ?? null);
            $startDate = trim((string) ($row['start_date'] ?? ''));
            $endDate = trim((string) ($row['end_date'] ?? ''));
            $causeNumber = $this->nullable($row['cause_number'] ?? null);
            $notes = $this->nullable($row['notes'] ?? null);

            if ($typeId === null && $institution === null && $startDate === '' && $endDate === ''
                && $causeNumber === null && $notes === null) {
                continue;
            }

            $items[] = [
                'measure_type_id' => $typeId,
                'institution' => $institution,
                'start_date' => $startDate !== '' ? $startDate : null,
                'end_date' => $endDate !== '' ? $endDate : null,
                'cause_number' => $causeNumber,
                'notes' => $notes,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{need_id: int, other_text: ?string}>
     */
    private function needItems(array $data): array
    {
        $raw = $data['need_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $others = is_array($data['need_other'] ?? null) ? $data['need_other'] : [];
        $items = [];

        foreach (array_unique(array_map('intval', $raw)) as $typeId) {
            if ($typeId < 1) {
                continue;
            }

            $slug = $this->catalogs->needSlug($typeId);
            $otherText = null;

            if ($slug === 'otra') {
                $otherText = trim((string) ($others[$typeId] ?? $others[(string) $typeId] ?? ''));
                $otherText = $otherText !== '' ? $otherText : null;
            }

            $items[] = [
                'need_id' => $typeId,
                'other_text' => $otherText,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{age_range_id: ?int, gender: ?string, notes: ?string}>
     */
    private function linkedMinorItems(array $data): array
    {
        $raw = $data['linked_minors'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $ageRangeId = $this->nullableInt($row['age_range_id'] ?? null);
            $gender = trim((string) ($row['gender'] ?? ''));
            $notes = $this->nullable($row['notes'] ?? null);

            if ($ageRangeId === null && $gender === '' && $notes === null) {
                continue;
            }

            $items[] = [
                'age_range_id' => $ageRangeId,
                'gender' => in_array($gender, ['female', 'male', 'other', 'unknown'], true) ? $gender : null,
                'notes' => $notes,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{
     *     id?: int,
     *     action_date: string,
     *     action_time: ?string,
     *     action_type_id: int,
     *     description: ?string,
     *     institution: ?string
     * }>
     */
    private function actionItems(array $data): array
    {
        $raw = $data['actions'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $typeId = (int) ($row['action_type_id'] ?? 0);
            $date = trim((string) ($row['action_date'] ?? ''));
            $time = trim((string) ($row['action_time'] ?? ''));
            $description = $this->nullable($row['description'] ?? null);
            $institution = $this->nullable($row['institution'] ?? null);
            $id = $this->nullableInt($row['id'] ?? null);

            if ($typeId < 1 && $date === '' && $time === '' && $description === null && $institution === null) {
                continue;
            }

            if ($time !== '') {
                if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
                    $time .= ':00';
                }
            } else {
                $time = null;
            }

            $item = [
                'action_date' => $date,
                'action_time' => $time,
                'action_type_id' => $typeId,
                'description' => $description,
                'institution' => $institution,
            ];

            if ($id !== null) {
                $item['id'] = $id;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function presentActions(array $rows): array
    {
        foreach ($rows as &$row) {
            $time = isset($row['action_time']) ? (string) $row['action_time'] : '';
            $row['action_time_short'] = $time !== '' ? substr($time, 0, 5) : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function presentFollowUps(array $rows): array
    {
        foreach ($rows as &$row) {
            $time = isset($row['follow_up_time']) ? (string) $row['follow_up_time'] : '';
            $row['follow_up_time_short'] = $time !== '' ? substr($time, 0, 5) : null;
            $row['requires_follow_up_label'] = self::binaryLabel(
                !empty($row['requires_follow_up']) ? 'yes' : 'no'
            );
            $row['is_pending'] = !empty($row['requires_follow_up'])
                && trim((string) ($row['next_follow_up_date'] ?? '')) !== '';
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{
     *     id?: int,
     *     referral_date: string,
     *     institution_id: ?int,
     *     program_area: ?string,
     *     reason: ?string,
     *     contact_person: ?string,
     *     referral_status_id: int,
     *     notes: ?string
     * }>
     */
    private function referralItems(array $data): array
    {
        $raw = $data['referrals'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $defaultStatusId = $this->catalogs->referralStatusId('pending');
        $items = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = trim((string) ($row['referral_date'] ?? ''));
            $institutionId = $this->nullableInt($row['institution_id'] ?? null);
            $programArea = $this->nullable($row['program_area'] ?? null);
            $reason = $this->nullable($row['reason'] ?? null);
            $contact = $this->nullable($row['contact_person'] ?? null);
            $notes = $this->nullable($row['notes'] ?? null);
            $statusId = $this->nullableInt($row['referral_status_id'] ?? null) ?? $defaultStatusId;
            $id = $this->nullableInt($row['id'] ?? null);

            if ($date === '' && $institutionId === null && $programArea === null
                && $reason === null && $contact === null && $notes === null) {
                continue;
            }

            $item = [
                'referral_date' => $date,
                'institution_id' => $institutionId,
                'program_area' => $programArea,
                'reason' => $reason,
                'contact_person' => $contact,
                'referral_status_id' => $statusId,
                'notes' => $notes,
            ];

            if ($id !== null) {
                $item['id'] = $id;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{
     *     id?: int,
     *     follow_up_date: string,
     *     follow_up_time: ?string,
     *     contact_type_id: ?int,
     *     contact_type_other: ?string,
     *     result_id: ?int,
     *     result_other: ?string,
     *     notes: ?string,
     *     requires_follow_up: int,
     *     next_follow_up_date: ?string
     * }>
     */
    private function followUpItems(array $data): array
    {
        $raw = $data['followups'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = trim((string) ($row['follow_up_date'] ?? ''));
            $time = trim((string) ($row['follow_up_time'] ?? ''));
            $contactTypeId = $this->nullableInt($row['contact_type_id'] ?? null);
            $resultId = $this->nullableInt($row['result_id'] ?? null);
            $notes = $this->nullable($row['notes'] ?? null);
            $id = $this->nullableInt($row['id'] ?? null);

            $contactOther = $this->nullable($row['contact_type_other'] ?? null);
            if ($contactTypeId !== null) {
                $contactSlug = $this->catalogs->followUpContactTypeSlug($contactTypeId);
                if ($contactSlug !== 'otro') {
                    $contactOther = null;
                }
            } else {
                $contactOther = null;
            }

            $resultOther = $this->nullable($row['result_other'] ?? null);
            if ($resultId !== null) {
                $resultSlug = $this->catalogs->followUpResultSlug($resultId);
                if ($resultSlug !== 'otro') {
                    $resultOther = null;
                }
            } else {
                $resultOther = null;
            }

            if ($date === '' && $time === '' && $contactTypeId === null && $resultId === null && $notes === null) {
                continue;
            }

            if ($time !== '') {
                if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
                    $time .= ':00';
                }
            } else {
                $time = null;
            }

            $requires = $this->binaryState($row['requires_follow_up'] ?? null) === 'yes' ? 1 : 0;
            $nextDate = trim((string) ($row['next_follow_up_date'] ?? ''));
            $nextFollowUpDate = ($requires === 1 && $nextDate !== '') ? $nextDate : null;

            $item = [
                'follow_up_date' => $date,
                'follow_up_time' => $time,
                'contact_type_id' => $contactTypeId,
                'contact_type_other' => $contactOther,
                'result_id' => $resultId,
                'result_other' => $resultOther,
                'notes' => $notes,
                'requires_follow_up' => $requires,
                'next_follow_up_date' => $nextFollowUpDate,
            ];

            if ($id !== null) {
                $item['id'] = $id;
            }

            $items[] = $item;
        }

        return $items;
    }

    private function binaryState(mixed $value): ?string
    {
        $value = trim((string) $value);

        return in_array($value, ['yes', 'no'], true) ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    private function priorityPayload(array $data, array $case): array
    {
        $priority = trim((string) ($data['priority'] ?? ''));

        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            return [
                'priority' => null,
                'priority_assigned_by' => null,
                'priority_assigned_at' => null,
            ];
        }

        $previous = (string) ($case['priority'] ?? '');
        if ($previous === $priority) {
            return [
                'priority' => $priority,
                'priority_assigned_by' => $case['priority_assigned_by'] ?? null,
                'priority_assigned_at' => $case['priority_assigned_at'] ?? null,
            ];
        }

        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        return [
            'priority' => $priority,
            'priority_assigned_by' => $userId,
            'priority_assigned_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{risk_factor_id: int, other_text: ?string}>
     */
    private function riskFactorItems(array $data): array
    {
        $raw = $data['risk_factor_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $others = is_array($data['risk_other'] ?? null) ? $data['risk_other'] : [];
        $items = [];

        foreach (array_unique(array_map('intval', $raw)) as $typeId) {
            if ($typeId < 1) {
                continue;
            }

            $slug = $this->catalogs->riskFactorSlug($typeId);
            $otherText = null;

            if ($slug === 'otro') {
                $otherText = trim((string) ($others[$typeId] ?? $others[(string) $typeId] ?? ''));
                $otherText = $otherText !== '' ? $otherText : null;
            }

            $items[] = [
                'risk_factor_id' => $typeId,
                'other_text' => $otherText,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $case
     */
    private function hasFacts(array $case): bool
    {
        return trim((string) ($case['description'] ?? '')) !== ''
            && ($case['violence_types'] ?? []) !== [];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function initials(array $row): string
    {
        $parts = array_filter([
            mb_substr(trim((string) ($row['first_names'] ?? '')), 0, 1),
            mb_substr(trim((string) ($row['paternal_surname'] ?? '')), 0, 1),
            mb_substr(trim((string) ($row['maternal_surname'] ?? '')), 0, 1),
        ]);

        return mb_strtoupper(implode('.', $parts) . (count($parts) > 0 ? '.' : ''));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        return AuditService::pick($row, [
            'id', 'case_number', 'reported_at', 'report_channel_id', 'report_channel_other',
            'incident_date_precision', 'incident_date', 'incident_time', 'incident_time_notes',
            'incident_place', 'incident_sector_id', 'incident_address', 'description',
            'relationship_type_id', 'relationship_other', 'current_relationship',
            'is_first_occurrence', 'occurrence_frequency', 'occurring_since', 'occurrence_notes',
            'has_previous_reports', 'has_formal_current_report',
            'has_protective_measures', 'has_linked_minors', 'has_dependents',
            'dependents_count', 'dependents_notes',
            'priority', 'priority_assigned_by', 'priority_assigned_at',
            'affected_person_id', 'case_status_id', 'created_by',
            'closed_at', 'cancelled_at',
        ]);
    }

    private function syncOperationalStatus(int $caseId): void
    {
        $case = $this->find($caseId);
        if ($this->isTerminal($case)) {
            return;
        }

        $slug = CaseStatus::REGISTERED;
        if ($this->caseHasPendingFollowUp($caseId)) {
            $slug = CaseStatus::FOLLOW_UP;
        } else {
            $detailed = $this->findDetailed($caseId);
            if (!empty($detailed['has_referrals'])) {
                $slug = CaseStatus::REFERRED;
            } elseif (!empty($detailed['has_facts'])) {
                $slug = CaseStatus::ACTIVE;
            }
        }

        if ((string) ($case['case_status_slug'] ?? '') !== $slug) {
            $this->cases->updateCaseStatus($caseId, $this->catalogs->statusId($slug));
        }
    }

    private function caseHasPendingFollowUp(int $caseId): bool
    {
        $followups = $this->presentFollowUps($this->followUps->forCase($caseId));
        if ($followups === []) {
            return false;
        }

        return !empty($followups[0]['is_pending']);
    }
}
