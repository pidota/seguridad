<?php

declare(strict_types=1);

/**
 * Pruebas funcionales del módulo Oficina de la Mujer.
 * Ejecutar: php tests/women_functional.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/women';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/seguridad/public/index.php';

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Middleware\PermissionMiddleware;
use App\Repositories\WomenOffice\CatalogRepository;
use App\Services\WomenOffice\PersonService;
use App\Services\WomenOffice\WomenCaseDocumentService;
use App\Services\WomenOffice\WomenCaseNumberService;
use App\Services\WomenOffice\WomenCaseService;
use App\Services\WomenOffice\WomenDashboardService;
use App\Services\WomenOffice\WomenFollowUpService;
use App\Services\WomenOffice\WomenHistoryService;
use App\Services\WomenOffice\WomenStatisticsService;
use App\Support\ChileanRutValidator;
use App\Validators\WomenOffice\PersonStoreValidator;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Request;
use Core\Session;

final class WomenFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $personIds = [];

    /** @var list<int> */
    private array $caseIds = [];

    /** @var list<int> */
    private array $roleIds = [];

    /** @var list<int> */
    private array $documentIds = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->assertPermissionDeniedWithoutAccess();
            $this->assertTablesExist();
            $this->assertCatalogSeeds();
            $this->assertCaseNumberGeneration();
            $this->assertPersonNotDuplicatedByRut();
            $this->assertCaseRegistrationStepOne();
            $this->assertCaseFactsAndViolence();
            $this->assertCaseAggressorAndRelationship();
            $this->assertCaseBackground();
            $this->assertCaseRiskAndPriority();
            $this->assertCaseSupport();
            $this->assertCaseActions();
            $this->assertCaseReferrals();
            $this->assertCaseFollowUps();
            $this->assertCaseDetailAndHistory();
            $this->assertCaseListingFilters();
            $this->assertDashboardAndFollowUpAgenda();
            $this->assertCaseStatistics();
            $this->assertCaseAccessAndSecurity();
            $this->assertCaseClosureDocumentsAndPersonEdit();
        } finally {
            $this->cleanup();
        }

        echo PHP_EOL . 'Resultado: ' . $this->passed . '/' . ($this->passed + $this->failed) . ' pruebas OK' . PHP_EOL;

        return $this->failed === 0 ? 0 : 1;
    }

    private function boot(): void
    {
        Session::start();
        Request::capture();

        $pdo = Database::connection();
        $adminId = (int) $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'superadministrador' AND u.is_active = 1
             LIMIT 1"
        )->fetchColumn();

        if ($adminId < 1) {
            throw new RuntimeException('No hay un superadministrador activo para las pruebas.');
        }

        Session::put('auth_user_id', $adminId);
        Auth::forgetCache();
        Permission::flush();
    }

    private function assertPermissionDeniedWithoutAccess(): void
    {
        $pdo = Database::connection();
        $roleId = (int) $pdo->query(
            "SELECT id FROM roles WHERE slug = 'operador_camaras' LIMIT 1"
        )->fetchColumn();

        if ($roleId < 1) {
            $this->check('Acceso 403', false);

            return;
        }

        $email = 'women.func.test.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        );
        $insert->execute([
            'name' => 'Prueba funcional Women',
            'email' => $email,
            'password' => password_hash('TestWomen123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $this->userIds[] = $userId;

        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);

        $previous = Auth::id();
        Session::put('auth_user_id', $userId);
        Auth::forgetCache();
        Permission::flush();

        $this->check('Usuario sin women.access no tiene el permiso', !Permission::has('women.access'));

        $status = null;
        try {
            (new PermissionMiddleware())->handle(Request::capture(), 'women.access');
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
        }

        $this->check('Usuario sin women.access recibe 403', $status === 403);

        $requireStatus = null;
        try {
            Permission::require('women.access');
        } catch (HttpException $e) {
            $requireStatus = $e->getStatusCode();
        }

        $this->check('Permission::require(women.access) lanza 403', $requireStatus === 403);

        Session::put('auth_user_id', $previous);
        Auth::forgetCache();
        Permission::flush();
    }

    private function assertTablesExist(): void
    {
        $pdo = Database::connection();
        $tables = [
            'women_case_sequences',
            'women_case_statuses',
            'women_people',
            'women_cases',
            'women_case_aggressors',
            'women_case_violence_types',
            'women_case_followups',
        ];

        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
            $this->check('Existe tabla ' . $table, $stmt !== false && $stmt->fetch() !== false);
        }
    }

    private function assertCatalogSeeds(): void
    {
        $pdo = Database::connection();
        $this->check(
            'Catálogo de estados incluye registrado',
            $this->catalogHasSlug($pdo, 'women_case_statuses', 'registered')
        );
        $this->check(
            'Catálogo de violencia incluye psicológica',
            $this->catalogHasSlug($pdo, 'women_violence_types', 'psicologica')
        );
        $this->check(
            'Catálogo de relación incluye pareja',
            $this->catalogHasSlug($pdo, 'women_relationship_types', 'pareja')
        );
    }

    private function assertCaseNumberGeneration(): void
    {
        $service = new WomenCaseNumberService();
        $year = (int) date('Y');
        $first = $service->next($year);
        $second = $service->next($year);

        $this->check('Número de caso tiene formato MUJER', preg_match('/^MUJER-' . $year . '-\\d{6}$/', $first) === 1);
        $this->check('Correlativo incrementa de forma segura', $first !== $second);
    }

    private function assertPersonNotDuplicatedByRut(): void
    {
        $people = new PersonService();
        $validator = new PersonStoreValidator();
        $rut = $this->unusedRut();

        $payload = [
            'first_names' => 'María Prueba',
            'paternal_surname' => 'Funcional',
            'maternal_surname' => 'Women',
            'rut' => ChileanRutValidator::format($rut) ?? $rut,
            'birth_date' => '1988-03-20',
            'phone' => '912345678',
            'safe_contact' => 'yes',
        ];

        $this->check('Alta de persona sin errores de validación', $validator->validate($payload) === []);

        $personId = $people->create($payload);
        $this->personIds[] = $personId;
        $this->check('Persona creada con ID válido', $personId > 0);

        $duplicateRejected = false;
        try {
            $people->create($payload);
        } catch (HttpException $e) {
            $duplicateRejected = $e->getStatusCode() === 422;
        }

        $this->check('No se duplica persona por RUT', $duplicateRejected);
    }

    private function assertCaseRegistrationStepOne(): void
    {
        $people = new PersonService();
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $rut = $this->unusedRut();

        $personId = $people->create([
            'first_names' => 'Laura',
            'paternal_surname' => 'Caso',
            'maternal_surname' => 'Prueba',
            'rut' => ChileanRutValidator::format($rut) ?? $rut,
            'birth_date' => '1992-11-05',
            'safe_contact' => 'restricted',
            'safe_contact_notes' => 'Contactar solo por teléfono laboral.',
        ]);
        $this->personIds[] = $personId;

        $channelId = 0;
        foreach ($catalogs->reportChannels() as $channel) {
            if (($channel['slug'] ?? '') === 'presencial') {
                $channelId = (int) $channel['id'];
                break;
            }
        }

        $this->check('Existe canal presencial para registro', $channelId > 0);

        $caseId = $cases->createRegistration($personId, [
            'reported_date' => date('Y-m-d'),
            'reported_time' => '10:30',
            'report_channel_id' => $channelId,
        ]);
        $this->caseIds[] = $caseId;

        $case = $cases->find($caseId);
        $this->check('Caso registrado con número MUJER', preg_match('/^MUJER-\\d{4}-\\d{6}$/', (string) ($case['case_number'] ?? '')) === 1);
        $this->check('Caso queda en estado registrado', ($case['case_status_slug'] ?? '') === 'registered');
        $this->check('Caso vincula a la persona afectada', (int) ($case['affected_person_id'] ?? 0) === $personId);
    }

    private function assertCaseFactsAndViolence(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCaseForFacts();

        $physicalId = 0;
        $psychId = 0;
        $otherId = 0;

        foreach ($catalogs->violenceTypes() as $type) {
            match ($type['slug'] ?? '') {
                'fisica' => $physicalId = (int) $type['id'],
                'psicologica' => $psychId = (int) $type['id'],
                'otra' => $otherId = (int) $type['id'],
                default => null,
            };
        }

        $this->check('Catálogo incluye violencia física y psicológica', $physicalId > 0 && $psychId > 0);

        $cases->updateFacts($caseId, [
            'incident_date_precision' => 'approximate',
            'incident_date' => '2026-07-10',
            'incident_time' => '21:15',
            'incident_time_notes' => 'Horario posterior al trabajo',
            'incident_place' => 'Domicilio',
            'incident_address' => 'Pasaje Los Aromos 120',
            'description' => 'Relato funcional de prueba con antecedentes del hecho denunciado.',
            'violence_type_ids' => [$physicalId, $psychId, $otherId],
            'violence_other' => [$otherId => 'Control coercitivo'],
        ]);

        $case = $cases->findDetailed($caseId);
        $this->check('Hechos quedan marcados como completos', !empty($case['has_facts']));
        $this->check('Descripción del hecho se persiste', str_contains((string) ($case['description'] ?? ''), 'Relato funcional'));
        $this->check('Se guardan múltiples tipos de violencia', count($case['violence_types'] ?? []) === 3);

        $validator = new \App\Validators\WomenOffice\CaseFactsValidator();
        $errors = $validator->validate([
            'incident_date_precision' => 'undetermined',
            'description' => 'Texto válido de prueba.',
            'violence_type_ids' => [],
        ]);
        $this->check('Validador exige al menos un tipo de violencia', isset($errors['violence_type_ids']));
    }

    private function assertCaseAggressorAndRelationship(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCaseForFacts();

        $parejaId = 0;
        $otroId = 0;
        foreach ($catalogs->relationshipTypes() as $type) {
            if (($type['slug'] ?? '') === 'pareja') {
                $parejaId = (int) $type['id'];
            }
            if (($type['slug'] ?? '') === 'otro') {
                $otroId = (int) $type['id'];
            }
        }

        $this->check('Catálogo incluye relación pareja', $parejaId > 0);

        $cases->updateAggressor($caseId, [
            'relationship_type_id' => $parejaId,
            'current_relationship' => 'yes',
            'aggressor_first_names' => 'Juan',
            'aggressor_paternal_surname' => 'Prueba',
            'aggressor_approximate_age' => '40 años',
            'aggressor_phone' => '987654321',
        ]);

        $case = $cases->findDetailed($caseId);
        $this->check('Relación queda registrada en el caso', (int) ($case['relationship_type_id'] ?? 0) === $parejaId);
        $this->check('Persona denunciada queda registrada', !empty($case['has_aggressor']));
        $this->check('Nombre de persona denunciada se persiste', ($case['aggressor']['full_name'] ?? '') === 'Juan Prueba');

        $validator = new \App\Validators\WomenOffice\CaseAggressorValidator();
        $errors = $validator->validate([
            'relationship_type_id' => $otroId,
            'relationship_other' => '',
        ]);
        $this->check('Validador exige especificar relación Otro', isset($errors['relationship_other']));
    }

    private function assertCaseBackground(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCaseForFacts();

        $carabinerosId = 0;
        foreach ($catalogs->formalReportInstitutions() as $institution) {
            if (($institution['slug'] ?? '') === 'carabineros') {
                $carabinerosId = (int) $institution['id'];
                break;
            }
        }

        $this->check('Catálogo incluye Carabineros para denuncia formal', $carabinerosId > 0);

        $cases->updateBackground($caseId, [
            'is_first_occurrence' => 'no',
            'occurrence_frequency' => 'Varias veces al mes',
            'occurring_since' => '2024',
            'has_previous_reports' => 'yes',
            'previous_reports' => [
                [
                    'institution_name' => 'Carabineros',
                    'report_date' => '2025-03-10',
                    'reference_number' => 'PART-123',
                    'notes' => 'Denuncia previa funcional',
                ],
            ],
            'has_formal_current_report' => 'yes',
            'formal_report_institution_id' => $carabinerosId,
            'formal_report_reference_number' => 'PART-456',
            'formal_report_date' => '2026-07-01',
        ]);

        $case = $cases->findDetailed($caseId);
        $this->check('Antecedentes quedan registrados', !empty($case['has_background']));
        $this->check('Recurrencia se persiste', ($case['is_first_occurrence'] ?? '') === 'no');
        $this->check('Se guarda denuncia anterior', count($case['previous_reports'] ?? []) === 1);
        $this->check('Se guarda denuncia formal actual', is_array($case['formal_report'] ?? null));

        $validator = new \App\Validators\WomenOffice\CaseBackgroundValidator();
        $errors = $validator->validate([
            'has_previous_reports' => 'yes',
            'previous_reports' => [],
        ]);
        $this->check('Validador exige antecedente si hubo denuncias previas', isset($errors['previous_reports']));
    }

    private function assertCaseRiskAndPriority(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCaseForFacts();

        $amenazasId = 0;
        $otroId = 0;
        foreach ($catalogs->riskFactors() as $factor) {
            if (($factor['slug'] ?? '') === 'amenazas') {
                $amenazasId = (int) $factor['id'];
            }
            if (($factor['slug'] ?? '') === 'otro') {
                $otroId = (int) $factor['id'];
            }
        }

        $this->check('Catálogo incluye factor amenazas', $amenazasId > 0);

        $cases->updateRiskPriority($caseId, [
            'priority' => 'high',
            'risk_factor_ids' => [$amenazasId, $otroId],
            'risk_other' => [$otroId => 'Seguimiento en domicilio'],
        ]);

        $case = $cases->findDetailed($caseId);
        $this->check('Evaluación de riesgo queda registrada', !empty($case['has_risk_assessment']));
        $this->check('Prioridad operativa se persiste', ($case['priority'] ?? '') === 'high');
        $this->check('Se guardan múltiples factores de riesgo', count($case['risk_factors'] ?? []) === 2);
        $this->check('Funcionario asignador de prioridad queda registrado', (int) ($case['priority_assigned_by'] ?? 0) > 0);

        $validator = new \App\Validators\WomenOffice\CaseRiskPriorityValidator();
        $errors = $validator->validate([
            'risk_factor_ids' => [$otroId],
            'risk_other' => [],
        ]);
        $this->check('Validador exige especificar factor Otro', isset($errors['risk_other_' . $otroId]));
    }

    private function assertCaseSupport(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCaseForFacts();

        $measureTypeId = 0;
        $orientacionId = 0;
        $otraNeedId = 0;
        $ageRangeId = 0;

        foreach ($catalogs->protectiveMeasureTypes() as $type) {
            if (($type['slug'] ?? '') === 'orden_proteccion') {
                $measureTypeId = (int) $type['id'];
            }
        }

        foreach ($catalogs->needs() as $need) {
            if (($need['slug'] ?? '') === 'orientacion') {
                $orientacionId = (int) $need['id'];
            }
            if (($need['slug'] ?? '') === 'otra') {
                $otraNeedId = (int) $need['id'];
            }
        }

        foreach ($catalogs->minorAgeRanges() as $range) {
            if (($range['slug'] ?? '') === '0_5') {
                $ageRangeId = (int) $range['id'];
            }
        }

        $this->check('Catálogo incluye medida de protección', $measureTypeId > 0);
        $this->check('Catálogo incluye necesidad orientación', $orientacionId > 0);

        $cases->updateSupport($caseId, [
            'has_protective_measures' => 'yes',
            'protective_measures' => [[
                'measure_type_id' => $measureTypeId,
                'institution' => 'Juzgado de Familia',
                'start_date' => '2026-01-10',
                'cause_number' => 'C-123-2026',
            ]],
            'need_ids' => [$orientacionId, $otraNeedId],
            'need_other' => [$otraNeedId => 'Acompañamiento en trámite'],
            'has_linked_minors' => 'yes',
            'linked_minors' => [[
                'age_range_id' => $ageRangeId,
                'gender' => 'female',
                'notes' => 'Convive en el hogar',
            ]],
            'has_dependents' => 'yes',
            'dependents_count' => 2,
            'dependents_notes' => 'Adulto mayor a cargo',
        ]);

        $case = $cases->findDetailed($caseId);
        $this->check('Contexto de apoyo queda registrado', !empty($case['has_support_context']));
        $this->check('Medidas de protección se persisten', count($case['protective_measures'] ?? []) === 1);
        $this->check('Se guardan múltiples necesidades', count($case['needs'] ?? []) === 2);
        $this->check('NNA vinculados quedan registrados', count($case['linked_minors'] ?? []) === 1);
        $this->check('Dependientes se persisten', (int) ($case['dependents_count'] ?? 0) === 2);

        $validator = new \App\Validators\WomenOffice\CaseSupportValidator();
        $errors = $validator->validate([
            'has_protective_measures' => 'yes',
            'protective_measures' => [],
            'need_ids' => [$otraNeedId],
            'need_other' => [],
        ]);
        $this->check('Validador exige medida si hay protección informada', isset($errors['protective_measures']));
        $this->check('Validador exige especificar necesidad Otra', isset($errors['need_other_' . $otraNeedId]));
    }

    private function assertCaseActions(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCaseForFacts();

        $orientacionId = 0;
        $otraId = 0;
        foreach ($catalogs->actionTypes() as $type) {
            if (($type['slug'] ?? '') === 'orientacion') {
                $orientacionId = (int) $type['id'];
            }
            if (($type['slug'] ?? '') === 'otra') {
                $otraId = (int) $type['id'];
            }
        }

        $this->check('Catálogo incluye acción orientación', $orientacionId > 0);

        $cases->updateActions($caseId, [
            'actions' => [
                [
                    'action_date' => date('Y-m-d'),
                    'action_time' => '10:30',
                    'action_type_id' => $orientacionId,
                    'description' => 'Se entregó información sobre rutas de denuncia.',
                    'institution' => 'Oficina de la Mujer',
                ],
                [
                    'action_date' => date('Y-m-d'),
                    'action_time' => '11:00',
                    'action_type_id' => $otraId,
                    'description' => 'Coordinación con vecina de confianza.',
                ],
            ],
        ]);

        $case = $cases->findDetailed($caseId);
        $this->check('Acciones quedan registradas', !empty($case['has_actions']));
        $this->check('Se guardan múltiples acciones', count($case['actions'] ?? []) === 2);
        $this->check('Funcionario creador queda registrado', (int) ($case['actions'][0]['created_by'] ?? 0) > 0);
        $slugs = array_column($case['actions'] ?? [], 'action_type_slug');
        $this->check('Tipo de acción se persiste', in_array('orientacion', $slugs, true));

        $validator = new \App\Validators\WomenOffice\CaseActionsValidator();
        $errors = $validator->validate([
            'actions' => [[
                'action_date' => date('Y-m-d'),
                'action_type_id' => $otraId,
                'description' => '',
            ]],
        ]);
        $this->check('Validador exige descripción para acción Otra', isset($errors['actions_0_description']));
    }

    private function assertCaseReferrals(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCaseForFacts();

        $cesfamId = 0;
        $otraId = 0;
        $pendingId = 0;
        foreach ($catalogs->referralInstitutions() as $institution) {
            if (($institution['slug'] ?? '') === 'cesfam') {
                $cesfamId = (int) $institution['id'];
            }
            if (($institution['slug'] ?? '') === 'otra') {
                $otraId = (int) $institution['id'];
            }
        }
        foreach ($catalogs->referralStatuses() as $status) {
            if (($status['slug'] ?? '') === 'pending') {
                $pendingId = (int) $status['id'];
            }
        }

        $this->check('Catálogo incluye institución CESFAM', $cesfamId > 0);
        $this->check('Catálogo incluye estado pendiente', $pendingId > 0);

        $cases->updateReferrals($caseId, [
            'referrals' => [
                [
                    'referral_date' => date('Y-m-d'),
                    'institution_id' => $cesfamId,
                    'program_area' => 'Programa VIF',
                    'referral_status_id' => $pendingId,
                    'reason' => 'Derivación por orientación jurídica.',
                    'contact_person' => 'Trabajadora social',
                ],
                [
                    'referral_date' => date('Y-m-d'),
                    'institution_id' => $otraId,
                    'program_area' => 'Fundación local',
                    'referral_status_id' => $pendingId,
                    'reason' => 'Apoyo habitacional.',
                ],
            ],
        ]);

        $case = $cases->findDetailed($caseId);
        $this->check('Derivaciones quedan registradas', !empty($case['has_referrals']));
        $this->check('Se guardan múltiples derivaciones', count($case['referrals'] ?? []) === 2);
        $this->check('Funcionario creador de derivación queda registrado', (int) ($case['referrals'][0]['created_by'] ?? 0) > 0);
        $this->check('Estado de derivación se persiste', ($case['referrals'][0]['referral_status_slug'] ?? '') === 'pending');

        $validator = new \App\Validators\WomenOffice\CaseReferralsValidator();
        $errors = $validator->validate([
            'referrals' => [[
                'referral_date' => date('Y-m-d'),
                'institution_id' => $otraId,
                'referral_status_id' => $pendingId,
                'program_area' => '',
            ]],
        ]);
        $this->check('Validador exige especificar institución Otra', isset($errors['referrals_0_program_area']));
    }

    private function assertCaseFollowUps(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCaseForFacts();

        $telefonicoId = 0;
        $contactoId = 0;
        $otroContactId = 0;
        foreach ($catalogs->followUpContactTypes() as $type) {
            if (($type['slug'] ?? '') === 'telefonico') {
                $telefonicoId = (int) $type['id'];
            }
            if (($type['slug'] ?? '') === 'otro') {
                $otroContactId = (int) $type['id'];
            }
        }
        foreach ($catalogs->followUpResults() as $result) {
            if (($result['slug'] ?? '') === 'contacto') {
                $contactoId = (int) $result['id'];
            }
        }

        $this->check('Catálogo incluye contacto telefónico', $telefonicoId > 0);
        $this->check('Catálogo incluye resultado contacto', $contactoId > 0);

        $cases->updateFollowUps($caseId, [
            'followups' => [
                [
                    'follow_up_date' => date('Y-m-d'),
                    'follow_up_time' => '15:00',
                    'contact_type_id' => $telefonicoId,
                    'result_id' => $contactoId,
                    'notes' => 'Contacto telefónico exitoso.',
                    'requires_follow_up' => 'yes',
                    'next_follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ],
                [
                    'follow_up_date' => date('Y-m-d'),
                    'contact_type_id' => $otroContactId,
                    'contact_type_other' => 'WhatsApp',
                    'result_id' => $contactoId,
                    'requires_follow_up' => 'no',
                ],
            ],
        ]);

        $case = $cases->findDetailed($caseId);
        $this->check('Seguimientos quedan registrados', !empty($case['has_followups']));
        $this->check('Se guardan múltiples seguimientos', count($case['followups'] ?? []) === 2);
        $this->check('Funcionario creador de seguimiento queda registrado', (int) ($case['followups'][0]['created_by'] ?? 0) > 0);
        $this->check('Próximo seguimiento se persiste', !empty($case['followups'][0]['is_pending']));

        $validator = new \App\Validators\WomenOffice\CaseFollowUpsValidator();
        $errors = $validator->validate([
            'followups' => [[
                'follow_up_date' => date('Y-m-d'),
                'contact_type_id' => $otroContactId,
                'contact_type_other' => '',
                'requires_follow_up' => 'yes',
            ]],
        ]);
        $this->check('Validador exige contacto Otro', isset($errors['followups_0_contact_other']));
        $this->check('Validador exige fecha próximo seguimiento', isset($errors['followups_0_next_date']));
    }

    private function assertCaseDetailAndHistory(): void
    {
        $cases = new WomenCaseService();
        $history = new WomenHistoryService();
        $caseId = $this->createCaseForFacts();

        $catalogs = new CatalogRepository();
        $actionTypeId = 0;
        $institutionId = 0;
        $telefonicoId = 0;
        $contactoId = 0;
        $pendingStatusId = 0;

        foreach ($catalogs->actionTypes() as $type) {
            if (($type['slug'] ?? '') === 'orientacion') {
                $actionTypeId = (int) $type['id'];
            }
        }
        foreach ($catalogs->referralInstitutions() as $institution) {
            if (($institution['slug'] ?? '') === 'cesfam') {
                $institutionId = (int) $institution['id'];
            }
        }
        foreach ($catalogs->followUpContactTypes() as $type) {
            if (($type['slug'] ?? '') === 'telefonico') {
                $telefonicoId = (int) $type['id'];
            }
        }
        foreach ($catalogs->followUpResults() as $result) {
            if (($result['slug'] ?? '') === 'contacto') {
                $contactoId = (int) $result['id'];
            }
        }
        foreach ($catalogs->referralStatuses() as $status) {
            if (($status['slug'] ?? '') === 'pending') {
                $pendingStatusId = (int) $status['id'];
            }
        }

        $cases->updateActions($caseId, [
            'actions' => [[
                'action_date' => date('Y-m-d'),
                'action_time' => '10:00',
                'action_type_id' => $actionTypeId,
                'description' => 'Orientación inicial.',
            ]],
        ]);
        $cases->updateReferrals($caseId, [
            'referrals' => [[
                'referral_date' => date('Y-m-d'),
                'institution_id' => $institutionId,
                'program_area' => 'Salud familiar',
                'referral_status_id' => $pendingStatusId,
            ]],
        ]);
        $cases->updateFollowUps($caseId, [
            'followups' => [[
                'follow_up_date' => date('Y-m-d'),
                'contact_type_id' => $telefonicoId,
                'result_id' => $contactoId,
                'requires_follow_up' => 'yes',
                'next_follow_up_date' => date('Y-m-d', strtotime('+7 days')),
            ]],
        ]);

        $case = $cases->findDetailed($caseId);
        $metrics = $history->metrics($case);
        $timeline = $history->timeline($case, 'desc');
        $kinds = array_column($timeline, 'kind');

        $this->check('Métricas incluyen última actuación', !empty($metrics['last_action']));
        $this->check('Métricas incluyen próximo seguimiento', !empty($metrics['next_follow_up']));
        $this->check('Timeline incluye registro del caso', in_array('registered', $kinds, true));
        $this->check('Timeline incluye acción', in_array('action', $kinds, true));
        $this->check('Timeline incluye derivación', in_array('referral', $kinds, true));
        $this->check('Timeline incluye seguimiento', in_array('followup', $kinds, true));
        $this->check('Timeline incluye próximo seguimiento', in_array('next_follow_up', $kinds, true));

        $auditEntries = $history->auditEntries($caseId);
        $this->check('Historial de auditoría incluye creación del caso', $auditEntries !== []);
    }

    private function assertCaseListingFilters(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCaseForFacts();
        $case = $cases->findDetailed($caseId);
        $caseNumber = (string) ($case['case_number'] ?? '');

        $fisicaId = 0;
        $psychId = 0;
        $telefonicoId = 0;
        $contactoId = 0;
        $cesfamId = 0;
        foreach ($catalogs->violenceTypes() as $type) {
            if (($type['slug'] ?? '') === 'fisica') {
                $fisicaId = (int) $type['id'];
            }
            if (($type['slug'] ?? '') === 'psicologica') {
                $psychId = (int) $type['id'];
            }
        }
        foreach ($catalogs->followUpContactTypes() as $type) {
            if (($type['slug'] ?? '') === 'telefonico') {
                $telefonicoId = (int) $type['id'];
            }
        }
        foreach ($catalogs->followUpResults() as $result) {
            if (($result['slug'] ?? '') === 'contacto') {
                $contactoId = (int) $result['id'];
            }
        }
        foreach ($catalogs->referralInstitutions() as $institution) {
            if (($institution['slug'] ?? '') === 'cesfam') {
                $cesfamId = (int) $institution['id'];
            }
        }

        $cases->updateFacts($caseId, [
            'incident_date_precision' => 'exact',
            'incident_date' => date('Y-m-d'),
            'description' => 'Hecho de prueba para listado.',
            'violence_type_ids' => array_values(array_filter([$fisicaId, $psychId])),
        ]);

        $cases->updateFollowUps($caseId, [
            'followups' => [[
                'follow_up_date' => date('Y-m-d'),
                'contact_type_id' => $telefonicoId,
                'result_id' => $contactoId,
                'requires_follow_up' => 'yes',
                'next_follow_up_date' => date('Y-m-d', strtotime('+3 days')),
            ]],
        ]);
        $cases->updateReferrals($caseId, [
            'referrals' => [[
                'referral_date' => date('Y-m-d'),
                'institution_id' => $cesfamId,
                'program_area' => 'Salud',
                'referral_status_id' => (int) ($catalogs->referralStatuses()[0]['id'] ?? 0),
            ]],
        ]);

        $byNumber = $cases->search(['case_number' => $caseNumber], 1);
        $this->check('Filtro por N.º caso encuentra el registro', count($byNumber['data']) >= 1);
        $this->check('Listado incluye tipos de violencia', ($byNumber['data'][0]['violence_types_label'] ?? '—') !== '—');

        if ($fisicaId > 0) {
            $byViolence = $cases->search(['violence_type_id' => $fisicaId], 1);
            $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $byViolence['data']);
            $this->check('Filtro por tipo de violencia funciona', in_array($caseId, $ids, true));
        }

        $pending = $cases->search(['pending_follow_up' => 'yes'], 1);
        $pendingIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $pending['data']);
        $this->check('Filtro por seguimiento pendiente funciona', in_array($caseId, $pendingIds, true));

        if ($cesfamId > 0) {
            $byReferral = $cases->search(['referral_institution_id' => $cesfamId], 1);
            $referralIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $byReferral['data']);
            $this->check('Filtro por institución de derivación funciona', in_array($caseId, $referralIds, true));
        }

        $this->check('Etiqueta de rango etario se calcula', WomenCaseService::ageRangeLabel(25) === '18–29');
        $this->check('Paginación devuelve metadatos', ($byNumber['pages'] ?? 0) >= 1);
    }

    private function assertDashboardAndFollowUpAgenda(): void
    {
        $dashboard = new WomenDashboardService();
        $followUps = new WomenFollowUpService();
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();

        $metrics = $dashboard->summaryMetrics();
        $this->check('Dashboard expone cinco indicadores', count($metrics) === 5);
        $this->check('Indicadores incluyen enlace operativo', isset($metrics[0]['path']) && $metrics[0]['path'] !== '');

        $alerts = $dashboard->alertCards();
        $this->check('Dashboard expone cuatro alertas', count($alerts) === 4);

        $caseId = $this->createCaseForFacts();
        $telefonicoId = 0;
        $contactoId = 0;
        foreach ($catalogs->followUpContactTypes() as $type) {
            if (($type['slug'] ?? '') === 'telefonico') {
                $telefonicoId = (int) $type['id'];
            }
        }
        foreach ($catalogs->followUpResults() as $result) {
            if (($result['slug'] ?? '') === 'contacto') {
                $contactoId = (int) $result['id'];
            }
        }

        $cases->updateFollowUps($caseId, [
            'followups' => [[
                'follow_up_date' => date('Y-m-d'),
                'contact_type_id' => $telefonicoId,
                'result_id' => $contactoId,
                'requires_follow_up' => 'yes',
                'next_follow_up_date' => date('Y-m-d'),
            ]],
        ]);

        $todayAlerts = $dashboard->alerts();
        $this->check('Alerta de seguimientos para hoy refleja datos', ($todayAlerts['due_today'] ?? 0) >= 1);

        $agenda = $followUps->agenda(['due' => 'today'], 1);
        $agendaIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $agenda['data']);
        $this->check('Agenda de seguimientos lista casos para hoy', in_array($caseId, $agendaIds, true));
    }

    private function assertCaseStatistics(): void
    {
        $statistics = new WomenStatisticsService();
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCaseForFacts();

        $fisicaId = 0;
        $parejaId = 0;
        $cesfamId = 0;
        $telefonicoId = 0;
        $contactoId = 0;
        $pendingStatusId = 0;

        foreach ($catalogs->violenceTypes() as $type) {
            if (($type['slug'] ?? '') === 'fisica') {
                $fisicaId = (int) $type['id'];
            }
        }
        foreach ($catalogs->relationshipTypes() as $type) {
            if (($type['slug'] ?? '') === 'pareja') {
                $parejaId = (int) $type['id'];
            }
        }
        foreach ($catalogs->referralInstitutions() as $institution) {
            if (($institution['slug'] ?? '') === 'cesfam') {
                $cesfamId = (int) $institution['id'];
            }
        }
        foreach ($catalogs->followUpContactTypes() as $type) {
            if (($type['slug'] ?? '') === 'telefonico') {
                $telefonicoId = (int) $type['id'];
            }
        }
        foreach ($catalogs->followUpResults() as $result) {
            if (($result['slug'] ?? '') === 'contacto') {
                $contactoId = (int) $result['id'];
            }
        }
        foreach ($catalogs->referralStatuses() as $status) {
            if (($status['slug'] ?? '') === 'pending') {
                $pendingStatusId = (int) $status['id'];
            }
        }

        $cases->updateFacts($caseId, [
            'incident_date_precision' => 'exact',
            'incident_date' => date('Y-m-d'),
            'description' => 'Hecho de prueba para estadísticas agregadas.',
            'violence_type_ids' => array_values(array_filter([$fisicaId])),
        ]);
        $cases->updateAggressor($caseId, [
            'relationship_type_id' => $parejaId,
            'current_relationship' => 'yes',
            'aggressor_first_names' => 'Carlos',
            'aggressor_paternal_surname' => 'Estadistica',
        ]);
        $cases->updateBackground($caseId, [
            'has_formal_current_report' => 'yes',
            'formal_report_institution_id' => (int) ($catalogs->formalReportInstitutions()[0]['id'] ?? 0),
            'formal_report_reference_number' => 'F-2026-001',
            'formal_report_date' => date('Y-m-d'),
        ]);
        $cases->updateRiskPriority($caseId, [
            'priority' => 'high',
        ]);
        $cases->updateReferrals($caseId, [
            'referrals' => [[
                'referral_date' => date('Y-m-d'),
                'institution_id' => $cesfamId,
                'program_area' => 'Salud',
                'referral_status_id' => $pendingStatusId > 0
                    ? $pendingStatusId
                    : (int) ($catalogs->referralStatuses()[0]['id'] ?? 0),
            ]],
        ]);
        $cases->updateFollowUps($caseId, [
            'followups' => [[
                'follow_up_date' => date('Y-m-d'),
                'contact_type_id' => $telefonicoId,
                'result_id' => $contactoId,
                'requires_follow_up' => 'yes',
                'next_follow_up_date' => date('Y-m-d', strtotime('+2 days')),
            ]],
        ]);

        $filters = $statistics->normalizeFilters([
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-d'),
        ]);
        $this->check('Filtros estadísticos normalizan fechas válidas', $filters['date_from'] <= $filters['date_to']);

        $swapped = $statistics->normalizeFilters([
            'date_from' => '2026-12-31',
            'date_to' => '2026-01-01',
        ]);
        $this->check('Filtros invierten rango de fechas invertido', $swapped['date_from'] === '2026-01-01');

        $summary = $statistics->summaryCards($filters);
        $this->check('Estadísticas exponen cinco tarjetas resumen', count($summary) === 5);
        $this->check('Resumen incluye casos del periodo', ($summary[0]['count'] ?? 0) >= 1);

        $tables = $statistics->tables($filters);
        $this->check('Estadísticas exponen once tablas agregadas', count($tables) === 11);

        $violenceTable = null;
        foreach ($tables as $table) {
            if (($table['title'] ?? '') === 'Tipo de violencia') {
                $violenceTable = $table;
                break;
            }
        }
        $this->check('Tabla de violencia existe', is_array($violenceTable));

        $violenceTotal = 0;
        foreach ($violenceTable['rows'] ?? [] as $row) {
            if (($row[0] ?? '') === 'Violencia física') {
                $violenceTotal = (int) ($row[1] ?? 0);
            }
        }
        $this->check('Estadísticas cuentan violencia física en el periodo', $violenceTotal >= 1);

        $serialized = json_encode($tables, JSON_THROW_ON_ERROR);
        $this->check('Tablas no incluyen RUT ni nombres de persona', !str_contains($serialized, 'Paula') && !str_contains($serialized, 'Hechos'));
        $this->check('Permiso women.statistics.view está definido', Permission::has('women.statistics.view'));
    }

    private function assertCaseAccessAndSecurity(): void
    {
        $cases = new WomenCaseService();
        $caseId = $this->createCaseForFacts();
        $case = $cases->findDetailed($caseId);
        $adminId = (int) (Auth::id() ?? 0);

        $consultaUserId = $this->createUserWithRole('consulta');
        Session::put('auth_user_id', $consultaUserId);
        Auth::forgetCache();
        Permission::flush();

        $viewDenied = false;
        try {
            $cases->assertCanView($case);
        } catch (HttpException $e) {
            $viewDenied = $e->getStatusCode() === 403;
        }
        $this->check('Usuario sin view_all no puede consultar caso ajeno', $viewDenied);

        $foreignSearch = $cases->search(['created_by' => $adminId], 1);
        $foreignIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $foreignSearch['data']);
        $this->check('Listado scoped ignora filtro created_by ajeno', !in_array($caseId, $foreignIds, true));

        $scopedUserId = $this->createScopedWomenOperator();
        Session::put('auth_user_id', $scopedUserId);
        Auth::forgetCache();
        Permission::flush();

        $editDenied = false;
        try {
            $cases->assertCanEdit($case);
        } catch (HttpException $e) {
            $editDenied = $e->getStatusCode() === 403;
        }
        $this->check('Operador scoped no puede editar caso ajeno', $editDenied);

        $ownCaseId = $this->createCaseForFacts();
        $ownList = $cases->search([], 1);
        $ownRow = null;
        foreach ($ownList['data'] as $row) {
            if ((int) ($row['id'] ?? 0) === $ownCaseId) {
                $ownRow = $row;
                break;
            }
        }
        $this->check('Operador scoped ve su propio caso en listado', is_array($ownRow));
        if (is_array($ownRow)) {
            $this->check(
                'Listado scoped muestra iniciales en lugar del nombre',
                ($ownRow['person_display_name'] ?? '') === ($ownRow['person_initials'] ?? '')
            );
        }

        Session::put('auth_user_id', $adminId);
        Auth::forgetCache();
        Permission::flush();
        $this->check('Autor del caso conserva acceso de edición', $cases->findDetailed($caseId)['id'] === $caseId);
    }

    private function assertCaseClosureDocumentsAndPersonEdit(): void
    {
        $cases = new WomenCaseService();
        $people = new PersonService();
        $documents = new WomenCaseDocumentService();
        $catalogs = new CatalogRepository();
        $adminId = (int) (Auth::id() ?? 0);
        $caseId = $this->createCaseForFacts();
        $case = $cases->findDetailed($caseId);
        $personId = (int) ($case['affected_person_id'] ?? 0);

        $physicalId = 0;
        $cesfamId = 0;
        $telefonicoId = 0;
        $contactoId = 0;
        $pendingStatusId = 0;

        foreach ($catalogs->violenceTypes() as $type) {
            if (($type['slug'] ?? '') === 'fisica') {
                $physicalId = (int) $type['id'];
            }
        }
        foreach ($catalogs->referralInstitutions() as $institution) {
            if (($institution['slug'] ?? '') === 'cesfam') {
                $cesfamId = (int) $institution['id'];
            }
        }
        foreach ($catalogs->followUpContactTypes() as $type) {
            if (($type['slug'] ?? '') === 'telefonico') {
                $telefonicoId = (int) $type['id'];
            }
        }
        foreach ($catalogs->followUpResults() as $result) {
            if (($result['slug'] ?? '') === 'contacto') {
                $contactoId = (int) $result['id'];
            }
        }
        foreach ($catalogs->referralStatuses() as $status) {
            if (($status['slug'] ?? '') === 'pending') {
                $pendingStatusId = (int) $status['id'];
            }
        }

        $cases->updateFacts($caseId, [
            'incident_date_precision' => 'approximate',
            'incident_date' => '2026-07-10',
            'incident_time' => '21:15',
            'incident_place' => 'Domicilio',
            'description' => 'Relato funcional para prueba de estados.',
            'violence_type_ids' => array_values(array_filter([$physicalId])),
        ]);
        $this->check('Hechos cambian estado operativo a activo', ($cases->find($caseId)['case_status_slug'] ?? '') === 'active');

        $cases->updateReferrals($caseId, [
            'referrals' => [[
                'referral_date' => date('Y-m-d'),
                'institution_id' => $cesfamId,
                'program_area' => 'Salud',
                'referral_status_id' => $pendingStatusId,
            ]],
        ]);
        $this->check('Derivaciones cambian estado operativo a derivado', ($cases->find($caseId)['case_status_slug'] ?? '') === 'referred');

        $cases->updateFollowUps($caseId, [
            'followups' => [[
                'follow_up_date' => date('Y-m-d'),
                'contact_type_id' => $telefonicoId,
                'result_id' => $contactoId,
                'requires_follow_up' => 'yes',
                'next_follow_up_date' => date('Y-m-d', strtotime('+5 days')),
            ]],
        ]);
        $this->check('Seguimiento pendiente cambia estado a follow_up', ($cases->find($caseId)['case_status_slug'] ?? '') === 'follow_up');

        $cases->closeCase($caseId, ['closure_notes' => 'Cierre funcional de prueba.']);
        $closed = $cases->findDetailed($caseId);
        $this->check('Caso cerrado queda en estado closed', ($closed['case_status_slug'] ?? '') === 'closed');
        $this->check('Caso cerrado registra fecha de cierre', !empty($closed['closed_at']));

        $closeAgainDenied = false;
        try {
            $cases->assertCanClose($closed);
        } catch (HttpException $e) {
            $closeAgainDenied = $e->getStatusCode() === 403;
        }
        $this->check('No se puede cerrar un caso ya finalizado', $closeAgainDenied);

        $scopedUserId = $this->createScopedWomenOperator();
        Session::put('auth_user_id', $scopedUserId);
        Auth::forgetCache();
        Permission::flush();

        $editClosedDenied = false;
        try {
            $cases->assertCanEdit($closed);
        } catch (HttpException $e) {
            $editClosedDenied = $e->getStatusCode() === 403;
        }
        $this->check('Operador sin edit_closed no puede editar caso cerrado', $editClosedDenied);

        $personEditDenied = false;
        try {
            $people->assertCanEdit($personId);
        } catch (HttpException $e) {
            $personEditDenied = $e->getStatusCode() === 403;
        }
        $this->check('Operador scoped no puede editar persona de caso ajeno', $personEditDenied);

        Session::put('auth_user_id', $adminId);
        Auth::forgetCache();
        Permission::flush();

        $cancelCaseId = $this->createCaseForFacts();
        $cases->cancelCase($cancelCaseId, ['cancellation_reason' => 'Anulación funcional de prueba por duplicado.']);
        $cancelled = $cases->findDetailed($cancelCaseId);
        $this->check('Caso anulado queda en estado cancelled', ($cancelled['case_status_slug'] ?? '') === 'cancelled');
        $this->check('Caso anulado registra motivo', str_contains((string) ($cancelled['cancellation_reason'] ?? ''), 'Anulación funcional'));

        $tmp = tempnam(sys_get_temp_dir(), 'wdoc');
        if ($tmp === false) {
            $this->check('Subida de documento PDF', false);
        } else {
            file_put_contents($tmp, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
            $documentId = $documents->upload($cancelCaseId, [
                'name' => 'respaldo-funcional.pdf',
                'tmp_name' => $tmp,
                'size' => filesize($tmp),
                'error' => UPLOAD_ERR_OK,
            ]);
            $this->documentIds[] = $documentId;
            @unlink($tmp);

            $listed = $documents->forCase($cancelCaseId);
            $this->check('Documento adjunto queda listado en el caso', count($listed) >= 1);

            $download = $documents->download($cancelCaseId, $documentId);
            $this->check('Descarga de documento resuelve ruta segura', is_file($download['absolute_path'] ?? ''));

            $documents->delete($cancelCaseId, $documentId);
            $this->check('Documento adjunto puede eliminarse', count($documents->forCase($cancelCaseId)) === 0);
        }

        $people->update($personId, [
            'first_names' => 'Paula Editada',
            'paternal_surname' => 'Hechos',
            'maternal_surname' => 'Prueba',
            'birth_date' => '1990-01-15',
            'safe_contact' => 'yes',
        ]);
        $updatedPerson = $people->find($personId);
        $this->check('Persona afectada puede editarse', ($updatedPerson['first_names'] ?? '') === 'Paula Editada');
    }

    private function createUserWithRole(string $roleSlug): int
    {
        $pdo = Database::connection();
        $roleId = (int) $pdo->query(
            'SELECT id FROM roles WHERE slug = ' . $pdo->quote($roleSlug) . ' LIMIT 1'
        )->fetchColumn();

        if ($roleId < 1) {
            throw new RuntimeException('Rol ' . $roleSlug . ' no encontrado.');
        }

        $email = 'women.sec.test.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        )->execute([
            'name' => 'Prueba seguridad Women',
            'email' => $email,
            'password' => password_hash('TestWomen123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $this->userIds[] = $userId;

        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);

        return $userId;
    }

    private function createScopedWomenOperator(): int
    {
        $pdo = Database::connection();
        $slug = 'women_scoped_test_' . bin2hex(random_bytes(3));
        $pdo->prepare(
            'INSERT INTO roles (slug, name, description, is_system)
             VALUES (:slug, :name, :description, 0)'
        )->execute([
            'slug' => $slug,
            'name' => 'Operador Women scoped test',
            'description' => 'Rol temporal de pruebas',
        ]);
        $roleId = (int) $pdo->lastInsertId();

        $permissionSlugs = [
            'women.access',
            'women.dashboard.view',
            'women.cases.view',
            'women.cases.create',
            'women.cases.edit',
        ];

        $permissionStmt = $pdo->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $assign = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');

        foreach ($permissionSlugs as $permissionSlug) {
            $permissionStmt->execute(['slug' => $permissionSlug]);
            $permissionId = (int) $permissionStmt->fetchColumn();
            if ($permissionId > 0) {
                $assign->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }

        $email = 'women.scoped.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        )->execute([
            'name' => 'Operador scoped Women',
            'email' => $email,
            'password' => password_hash('TestWomen123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $this->userIds[] = $userId;

        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);

        $this->roleIds[] = $roleId;

        return $userId;
    }

    private function createCaseForFacts(): int
    {
        $people = new PersonService();
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $rut = $this->unusedRut();

        $personId = $people->create([
            'first_names' => 'Paula',
            'paternal_surname' => 'Hechos',
            'maternal_surname' => 'Prueba',
            'rut' => ChileanRutValidator::format($rut) ?? $rut,
            'birth_date' => '1990-01-15',
            'safe_contact' => 'yes',
        ]);
        $this->personIds[] = $personId;

        $channelId = 0;
        foreach ($catalogs->reportChannels() as $channel) {
            if (($channel['slug'] ?? '') === 'presencial') {
                $channelId = (int) $channel['id'];
                break;
            }
        }

        $caseId = $cases->createRegistration($personId, [
            'reported_date' => date('Y-m-d'),
            'reported_time' => '09:00',
            'report_channel_id' => $channelId,
        ]);
        $this->caseIds[] = $caseId;

        return $caseId;
    }

    private function unusedRut(): string
    {
        $people = new PersonService();

        for ($body = 77000000; $body <= 77999999; $body++) {
            $normalized = ChileanRutValidator::normalize((string) $body . $this->verifier((string) $body));
            if ($normalized === null) {
                continue;
            }

            $lookup = $people->lookup(ChileanRutValidator::format($normalized) ?? $normalized);
            if (empty($lookup['exists'])) {
                return $normalized;
            }
        }

        throw new RuntimeException('No se encontró un RUT libre para la prueba.');
    }

    private function verifier(string $body): string
    {
        $sum = 0;
        $factor = 2;

        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += (int) $body[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $mod = 11 - ($sum % 11);

        return match ($mod) {
            11 => '0',
            10 => 'K',
            default => (string) $mod,
        };
    }

    private function catalogHasSlug(\PDO $pdo, string $table, string $slug): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return (bool) $stmt->fetchColumn();
    }

    private function check(string $label, bool $ok): void
    {
        if ($ok) {
            $this->passed++;
            echo '  PASS  ' . $label . PHP_EOL;

            return;
        }

        $this->failed++;
        echo '  FAIL  ' . $label . PHP_EOL;
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach ($this->caseIds as $caseId) {
            $pdo->prepare('DELETE FROM women_case_documents WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_protective_measures WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_needs WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_linked_minors WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_actions WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_referrals WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_followups WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_previous_reports WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_formal_reports WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_risk_factors WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_violence_types WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_aggressors WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_cases WHERE id = :id')->execute(['id' => $caseId]);
        }

        foreach ($this->personIds as $personId) {
            $pdo->prepare('DELETE FROM women_people WHERE id = :id')->execute(['id' => $personId]);
        }

        if ($this->userIds === []) {
            return;
        }

        foreach ($this->roleIds as $roleId) {
            $pdo->prepare('DELETE FROM user_roles WHERE role_id = :id')->execute(['id' => $roleId]);
            $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :id')->execute(['id' => $roleId]);
            $pdo->prepare('DELETE FROM roles WHERE id = :id')->execute(['id' => $roleId]);
        }

        $delete = $pdo->prepare('DELETE FROM users WHERE id = :id');

        foreach ($this->userIds as $userId) {
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $userId]);
            $delete->execute(['id' => $userId]);
        }
    }
}

exit((new WomenFunctionalTests())->run());
