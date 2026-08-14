<?php

declare(strict_types=1);

/**
 * Pruebas funcionales del módulo SENDA.
 * Ejecutar: php tests/senda_functional.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/senda';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/seguridad/public/index.php';

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Middleware\PermissionMiddleware;
use App\Repositories\Senda\AssistResultRepository;
use App\Services\Senda\AssistClassificationService;
use App\Services\Senda\AttentionService;
use App\Services\Senda\DemandOrigin;
use App\Services\Senda\EntryType;
use App\Services\Senda\FollowUpService;
use App\Services\Senda\HistoryService;
use App\Services\Senda\PersonService;
use App\Services\Senda\ReferralInstitutionType;
use App\Services\Senda\ReferralService;
use App\Services\Senda\ReferralStatus;
use App\Support\ChileanRutValidator;
use App\Validators\Senda\AttentionStoreValidator;
use App\Validators\Senda\FollowUpSearchValidator;
use App\Validators\Senda\FollowUpStoreValidator;
use App\Validators\Senda\PersonStoreValidator;
use App\Validators\Senda\ReferralStoreValidator;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Request;
use Core\Session;

final class SendaFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $personIds = [];

    /** @var list<int> */
    private array $attentionIds = [];

    /** @var list<int> */
    private array $referralIds = [];

    /** @var list<int> */
    private array $followUpIds = [];

    /** @var list<int> */
    private array $userIds = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testAccessWithoutPermission();
            $this->testRutDoesNotDuplicate();
            $this->testDerivacionShowsAndRequiresFields();
            $this->testDemandaEspontaneaDoesNotRequireReferral();
            $this->testCesfam();
            $this->testTreatmentsIgnoredWhenNo();
            $this->testScreeningNoFinalizesWithoutAssist();
            $this->testScreeningYesShowsAssistAndPersists();
            $this->testAssistThresholds();
            $this->testFollowUpNextDate();
            $this->testPersonHistoryDossier();
        } catch (\Throwable $e) {
            $this->fail('ejecución', $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        }

        try {
            $this->cleanup();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Aviso: no se pudo limpiar todo el residuo de prueba: ' . $e->getMessage() . PHP_EOL);
        }
        $this->printSummary();

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

    private function testAccessWithoutPermission(): void
    {
        $pdo = Database::connection();
        $roleId = (int) $pdo->query(
            "SELECT id FROM roles WHERE slug = 'operador_camaras' LIMIT 1"
        )->fetchColumn();

        if ($roleId < 1) {
            $this->fail('Acceso 403', 'No existe el rol operador_camaras.');
            return;
        }

        $email = 'senda.func.test.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        );
        $insert->execute([
            'name' => 'Prueba funcional SENDA',
            'email' => $email,
            'password' => password_hash('TestSenda123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $this->userIds[] = $userId;

        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);

        $previous = Auth::id();
        Session::put('auth_user_id', $userId);
        Auth::forgetCache();
        Permission::flush();

        $this->assertFalse(Permission::has('senda.access'), 'Usuario sin senda.access no tiene el permiso');

        $status = null;
        try {
            (new PermissionMiddleware())->handle(Request::capture(), 'senda.access');
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
        }

        $this->assertSame(403, $status, 'Usuario sin senda.access recibe 403');

        $requireStatus = null;
        try {
            Permission::require('senda.access');
        } catch (HttpException $e) {
            $requireStatus = $e->getStatusCode();
        }
        $this->assertSame(403, $requireStatus, 'Permission::require(senda.access) lanza 403');

        Session::put('auth_user_id', $previous);
        Auth::forgetCache();
        Permission::flush();
    }

    private function testRutDoesNotDuplicate(): void
    {
        $people = new PersonService();
        $validator = new PersonStoreValidator();
        $rut = $this->unusedRut();

        $payload = [
            'first_names' => 'Ana Prueba',
            'paternal_surname' => 'Funcional',
            'maternal_surname' => 'Senda',
            'rut' => ChileanRutValidator::format($rut) ?? $rut,
            'birth_date' => '1990-05-12',
            'address' => 'Calle Test 100',
            'phone' => '912345678',
            'email' => 'ana.funcional@example.test',
            'education' => 'Media',
            'occupation' => 'Dueña de casa',
        ];

        $this->assertSame([], $validator->validate($payload), 'Alta de persona con RUT válido no tiene errores');

        $id = $people->create($payload);
        $this->personIds[] = $id;

        $status = null;
        $message = '';
        try {
            $people->create($payload);
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $message = $e->getMessage();
        }

        $this->assertSame(422, $status, 'RUT duplicado rechaza el alta');
        $this->assertTrue(
            str_contains(mb_strtolower($message), 'ya existe'),
            'El mensaje de RUT duplicado indica que la persona ya existe'
        );
    }

    private function testDerivacionShowsAndRequiresFields(): void
    {
        $form = file_get_contents(BASE_PATH . '/app/Views/senda/attentions/form.php') ?: '';
        $js = file_get_contents(BASE_PATH . '/resources/js/modules/senda/attention.js') ?: '';

        foreach ([
            'data-senda-referral-panel',
            'referral_institution_type',
            'referral_institution_name',
            'referral_person',
            'referral_phone',
            'referral_email',
            'referral_notes',
        ] as $needle) {
            $this->assertTrue(str_contains($form, $needle), 'Formulario de derivación incluye ' . $needle);
        }

        $this->assertTrue(str_contains($js, "entryType() === 'derivacion'"), 'JS muestra el panel solo en derivación');

        $originOptions = DemandOrigin::optionsForEntryType(EntryType::DERIVACION);
        $originValues = array_column($originOptions, 'value');
        $this->assertFalse(in_array(DemandOrigin::ESPONTANEA, $originValues, true), 'Derivación no ofrece origen espontánea');
        $this->assertSame(
            ReferralInstitutionType::values(),
            $originValues,
            'Derivación ofrece los tipos de institución'
        );

        $errors = (new AttentionStoreValidator())->validate([
            'entry_type' => EntryType::DERIVACION,
            'senda_person_id' => '1',
            'attention_date' => date('Y-m-d'),
            'attention_time' => '10:00',
            'summary' => 'Sin datos de derivación',
        ]);

        foreach (['referral_institution_type', 'referral_institution_name', 'referral_person'] as $field) {
            $this->assertTrue(isset($errors[$field]), 'Derivación exige el campo ' . $field);
        }

        $ok = (new AttentionStoreValidator())->validate([
            'entry_type' => EntryType::DERIVACION,
            'senda_person_id' => '1',
            'attention_date' => date('Y-m-d'),
            'attention_time' => '10:00',
            'referral_institution_type' => ReferralInstitutionType::CENTRO_SALUD,
            'referral_institution_name' => 'CESFAM Centro',
            'referral_person' => 'Dra. Pérez',
        ]);
        $this->assertSame([], $ok, 'Derivación completa pasa validación');

        $personId = $this->ensurePerson();
        $attentionId = (new AttentionService())->create([
            'entry_type' => EntryType::DERIVACION,
            'senda_person_id' => (string) $personId,
            'attention_date' => date('Y-m-d'),
            'attention_time' => '10:15',
            'referral_institution_type' => ReferralInstitutionType::CENTRO_SALUD,
            'referral_institution_name' => 'CESFAM Centro',
            'referral_person' => 'Dra. Pérez',
            'referral_phone' => '221234567',
            'referral_email' => 'perez@salud.test',
            'referral_notes' => 'Derivada por consumo',
            'summary' => 'Atención de prueba derivación',
        ]);
        $this->attentionIds[] = $attentionId;

        $saved = (new AttentionService())->find($attentionId);
        $this->assertSame(EntryType::DERIVACION, $saved['entry_type'], 'Atención queda como derivación');
        $this->assertSame(ReferralInstitutionType::CENTRO_SALUD, $saved['referral_institution_type'], 'Guarda tipo de institución');
        $this->assertSame('CESFAM Centro', $saved['referral_institution_name'], 'Guarda nombre de institución');
        $this->assertSame('Dra. Pérez', $saved['referral_person'], 'Guarda profesional que deriva');
    }

    private function testDemandaEspontaneaDoesNotRequireReferral(): void
    {
        $errors = (new AttentionStoreValidator())->validate([
            'entry_type' => EntryType::DEMANDA_ESPONTANEA,
            'senda_person_id' => '1',
            'attention_date' => date('Y-m-d'),
            'attention_time' => '11:00',
            'summary' => 'Llega por su cuenta',
        ]);
        $this->assertSame([], $errors, 'Demanda espontánea no exige campos de derivación');
        $this->assertTrue(DemandOrigin::isLocked(EntryType::DEMANDA_ESPONTANEA), 'Origen queda bloqueado en espontánea');
        $this->assertSame(
            [['value' => DemandOrigin::ESPONTANEA, 'label' => DemandOrigin::label(DemandOrigin::ESPONTANEA)]],
            DemandOrigin::optionsForEntryType(EntryType::DEMANDA_ESPONTANEA),
            'Demanda espontánea solo ofrece origen espontánea'
        );

        $personId = $this->ensurePerson();
        $attentionId = (new AttentionService())->create([
            'entry_type' => EntryType::DEMANDA_ESPONTANEA,
            'senda_person_id' => (string) $personId,
            'attention_date' => date('Y-m-d'),
            'attention_time' => '11:20',
            'referral_institution_type' => ReferralInstitutionType::CENTRO_SALUD,
            'referral_institution_name' => 'No debería guardarse',
            'referral_person' => 'No debería guardarse',
            'referral_phone' => '999999999',
            'referral_email' => 'ignorar@test.local',
            'referral_notes' => 'Ignorar',
            'summary' => 'Demanda espontánea de prueba',
        ]);
        $this->attentionIds[] = $attentionId;

        $saved = (new AttentionService())->find($attentionId);
        $this->assertSame(EntryType::DEMANDA_ESPONTANEA, $saved['entry_type'], 'Atención queda como demanda espontánea');
        $this->assertTrue(
            $saved['referral_institution_type'] === null || $saved['referral_institution_type'] === '',
            'Demanda espontánea ignora tipo de institución'
        );
        $this->assertTrue(
            $saved['referral_institution_name'] === null || $saved['referral_institution_name'] === '',
            'Demanda espontánea ignora nombre de institución'
        );
        $this->assertTrue(
            $saved['referral_person'] === null || $saved['referral_person'] === '',
            'Demanda espontánea ignora profesional que deriva'
        );
        $this->assertSame(
            DemandOrigin::ESPONTANEA,
            DemandOrigin::fromAttention($saved),
            'El origen de demanda queda espontánea'
        );
    }

    private function testCesfam(): void
    {
        $validator = new ReferralStoreValidator();
        $base = $this->referralValidatorBase();

        $missing = $validator->validate(array_merge($base, [
            'enrolled_health_center' => 'si',
            'cesfam_name' => '',
        ]));
        $this->assertTrue(isset($missing['cesfam_name']), 'CESFAM Sí exige el nombre');

        $ok = $validator->validate(array_merge($base, [
            'enrolled_health_center' => 'si',
            'cesfam_name' => 'CESFAM Los Álamos',
        ]));
        $this->assertFalse(isset($ok['cesfam_name']), 'CESFAM Sí con nombre pasa validación');

        $ignored = $validator->validate(array_merge($base, [
            'enrolled_health_center' => 'no',
            'cesfam_name' => '',
        ]));
        $this->assertFalse(isset($ignored['cesfam_name']), 'CESFAM No no exige el nombre');

        $attentionId = $this->createAttention(EntryType::DERIVACION);
        $referralId = (new ReferralService())->create($this->referralStorePayload($attentionId, [
            'enrolled_health_center' => 'no',
            'cesfam_name' => 'Este nombre debe ignorarse',
            'has_previous_treatments' => 'no',
            'previous_treatments_count' => '4',
            'previous_treatment_modality' => 'residencial',
            'previous_treatment_stay' => '1_3_meses',
            'previous_treatment_completed' => 'si',
            'previous_treatment_center' => 'Centro X',
            'previous_treatment_commune' => 'Santiago',
            'screening_used' => 'no',
            'finalize_referral' => '1',
        ]));
        $this->referralIds[] = $referralId;

        $saved = (new ReferralService())->find($referralId);
        $this->assertSame('no', (string) $saved['enrolled_health_center'], 'CESFAM No queda registrado');
        $this->assertTrue(
            $saved['cesfam_name'] === null || $saved['cesfam_name'] === '',
            'CESFAM No ignora el nombre enviado'
        );

        $siAttention = $this->createAttention(EntryType::DERIVACION);
        $siId = (new ReferralService())->create($this->referralStorePayload($siAttention, [
            'enrolled_health_center' => 'si',
            'cesfam_name' => 'CESFAM Los Álamos',
            'has_previous_treatments' => 'no',
            'screening_used' => 'no',
            'save_draft' => '1',
        ]));
        $this->referralIds[] = $siId;
        $siSaved = (new ReferralService())->find($siId);
        $this->assertSame('CESFAM Los Álamos', $siSaved['cesfam_name'], 'CESFAM Sí persiste el nombre');
    }

    private function testTreatmentsIgnoredWhenNo(): void
    {
        $validator = new ReferralStoreValidator();
        $base = $this->referralValidatorBase();

        $noExtra = $validator->validate(array_merge($base, [
            'has_previous_treatments' => 'no',
            'previous_treatments_count' => '9',
            'previous_treatment_center' => 'Debe ignorarse',
        ]));
        $this->assertFalse(isset($noExtra['previous_treatments_count']), 'Tratamientos No no exige cantidad');
        $this->assertFalse(isset($noExtra['previous_treatment_center']), 'Tratamientos No no exige centro');

        $yesMissing = $validator->validate(array_merge($base, [
            'has_previous_treatments' => 'si',
        ]));
        $this->assertTrue(isset($yesMissing['previous_treatments_count']), 'Tratamientos Sí exige cantidad');

        $referral = (new ReferralService())->find($this->referralIds[0]);
        $this->assertSame('no', $referral['has_previous_treatments'], 'Tratamientos No queda en no');
        $this->assertTrue(
            $referral['previous_treatments_count'] === null || $referral['previous_treatments_count'] === '',
            'Tratamientos No ignora la cantidad enviada'
        );
        $this->assertTrue(
            ($referral['previous_treatment_modality'] ?? null) === null || $referral['previous_treatment_modality'] === '',
            'Tratamientos No ignora la modalidad'
        );
        $this->assertTrue(
            ($referral['previous_treatment_center'] ?? null) === null || $referral['previous_treatment_center'] === '',
            'Tratamientos No ignora el centro'
        );
    }

    private function testScreeningNoFinalizesWithoutAssist(): void
    {
        $form = file_get_contents(BASE_PATH . '/app/Views/senda/referrals/form.php') ?: '';
        $js = file_get_contents(BASE_PATH . '/resources/js/modules/senda/assist.js') ?: '';

        $this->assertTrue(str_contains($form, 'data-senda-assist-panel'), 'La ficha incluye el panel ASSIST');
        $this->assertTrue(str_contains($form, 'data-senda-screening-end'), 'Tamizaje NO muestra el cierre de ficha');
        $this->assertTrue(str_contains($js, "value === 'si'"), 'JS muestra ASSIST cuando el tamizaje es Sí');
        $this->assertTrue(str_contains($js, 'screeningEnd.hidden = !skipped'), 'JS cierra la ficha cuando el tamizaje es No');

        $errors = (new ReferralStoreValidator())->validate(array_merge($this->referralValidatorBase(), [
            'screening_used' => 'no',
            'assist' => [
                'tabaco' => ['score' => '99'],
            ],
        ]));
        $this->assertFalse(isset($errors['assist.tabaco.score']), 'Tamizaje NO no valida ASSIST');

        $referral = (new ReferralService())->find($this->referralIds[0]);
        $this->assertSame('no', $referral['screening_used'], 'Tamizaje NO queda persistido');
        $this->assertSame(0, (int) $referral['assist_applicable'], 'Tamizaje NO no aplica ASSIST');
        $this->assertSame(ReferralStatus::COMPLETED, $referral['status'], 'Tamizaje NO permite finalizar la ficha');

        $assistRows = (new AssistResultRepository())->forReferral((int) $referral['id']);
        $this->assertSame([], $assistRows, 'Tamizaje NO no requiere ni guarda ASSIST');
    }

    private function testScreeningYesShowsAssistAndPersists(): void
    {
        $errorsEmpty = (new ReferralStoreValidator())->validate(array_merge($this->referralValidatorBase(), [
            'screening_used' => 'si',
        ]));
        $this->assertFalse(isset($errorsEmpty['assist.tabaco.score']), 'ASSIST permite puntajes vacíos');

        $errorsInvalid = (new ReferralStoreValidator())->validate(array_merge($this->referralValidatorBase(), [
            'screening_used' => 'si',
            'assist' => ['tabaco' => ['score' => '40']],
        ]));
        $this->assertTrue(isset($errorsInvalid['assist.tabaco.score']), 'ASSIST rechaza puntaje sobre 39');

        $attentionId = $this->createAttention(EntryType::DERIVACION);
        $referralId = (new ReferralService())->create($this->referralStorePayload($attentionId, [
            'enrolled_health_center' => 'no',
            'has_previous_treatments' => 'no',
            'screening_used' => 'si',
            'assist' => [
                'tabaco' => ['score' => '21'],
                'alcohol' => ['score' => '10'],
                'marihuana' => ['score' => '4'],
            ],
            'finalize_referral' => '1',
        ]));
        $this->referralIds[] = $referralId;

        $saved = (new ReferralService())->find($referralId);
        $this->assertSame('si', $saved['screening_used'], 'Tamizaje SÍ queda persistido');
        $this->assertSame(1, (int) $saved['assist_applicable'], 'Tamizaje SÍ aplica ASSIST');
        $this->assertSame('21', (string) $saved['assist']['tabaco']['score'], 'ASSIST guarda puntaje de tabaco');
        $this->assertSame(AssistClassificationService::TRATAMIENTO, $saved['assist']['tabaco']['risk_level'], 'Tabaco 21 → Tratamiento');
        $this->assertSame(AssistClassificationService::MINIMA, $saved['assist']['alcohol']['risk_level'], 'Alcohol 10 → Mínima');
        $this->assertSame(AssistClassificationService::BREVE, $saved['assist']['marihuana']['risk_level'], 'Marihuana 4 → Breve');
    }

    private function testAssistThresholds(): void
    {
        $service = new AssistClassificationService();
        $cases = [
            ['tabaco', 3, AssistClassificationService::MINIMA, 'Tabaco 3 → Mínima'],
            ['tabaco', 4, AssistClassificationService::BREVE, 'Tabaco 4 → Breve'],
            ['tabaco', 20, AssistClassificationService::BREVE, 'Tabaco 20 → Breve'],
            ['tabaco', 21, AssistClassificationService::TRATAMIENTO, 'Tabaco 21 → Tratamiento'],
            ['alcohol', 10, AssistClassificationService::MINIMA, 'Alcohol 10 → Mínima'],
            ['alcohol', 11, AssistClassificationService::BREVE, 'Alcohol 11 → Breve'],
            ['alcohol', 20, AssistClassificationService::BREVE, 'Alcohol 20 → Breve'],
            ['alcohol', 21, AssistClassificationService::TRATAMIENTO, 'Alcohol 21 → Tratamiento'],
            ['marihuana', 3, AssistClassificationService::MINIMA, 'Marihuana 3 → Mínima'],
            ['marihuana', 4, AssistClassificationService::BREVE, 'Marihuana 4 → Breve'],
            ['marihuana', 20, AssistClassificationService::BREVE, 'Marihuana 20 → Breve'],
            ['marihuana', 21, AssistClassificationService::TRATAMIENTO, 'Marihuana 21 → Tratamiento'],
        ];

        foreach ($cases as [$substance, $score, $expected, $label]) {
            $this->assertSame($expected, $service->classify($substance, $score), $label);
        }
    }

    private function testFollowUpNextDate(): void
    {
        $validator = new FollowUpStoreValidator();
        $attentionId = $this->attentionIds[0] ?? $this->createAttention(EntryType::DERIVACION);

        $missing = $validator->validate([
            'senda_attention_id' => (string) $attentionId,
            'follow_up_date' => date('Y-m-d'),
            'follow_up_time' => '09:00',
            'contact_type' => 'telefonico',
            'result' => 'contacto_exitoso',
            'requires_follow_up' => 'si',
        ]);
        $this->assertTrue(isset($missing['next_follow_up_date']), 'Requiere seguimiento = Sí exige próxima fecha');

        $noDate = $validator->validate([
            'senda_attention_id' => (string) $attentionId,
            'follow_up_date' => date('Y-m-d'),
            'contact_type' => 'telefonico',
            'result' => 'seguimiento_finalizado',
            'requires_follow_up' => 'no',
            'next_follow_up_date' => date('Y-m-d', strtotime('+7 days')),
        ]);
        $this->assertFalse(isset($noDate['next_follow_up_date']), 'Requiere seguimiento = No no exige próxima fecha');

        $yesId = (new FollowUpService())->create([
            'senda_attention_id' => (string) $attentionId,
            'follow_up_date' => date('Y-m-d'),
            'follow_up_time' => '09:30',
            'contact_type' => 'telefonico',
            'result' => 'continua_en_seguimiento',
            'notes' => 'Queda citado',
            'requires_follow_up' => 'si',
            'next_follow_up_date' => date('Y-m-d', strtotime('+7 days')),
        ]);
        $this->followUpIds[] = $yesId;
        $yes = (new FollowUpService())->find($yesId);
        $expectedDate = date('Y-m-d', strtotime('+7 days'));
        $this->assertSame('si', $yes['requires_follow_up'], 'Seguimiento Sí queda marcado');
        $this->assertSame($expectedDate, substr((string) $yes['next_follow_up_date'], 0, 10), 'Seguimiento Sí guarda la próxima fecha');

        $noId = (new FollowUpService())->create([
            'senda_attention_id' => (string) $attentionId,
            'follow_up_date' => date('Y-m-d'),
            'follow_up_time' => '10:00',
            'contact_type' => 'presencial',
            'result' => 'seguimiento_finalizado',
            'notes' => 'Cierra seguimiento',
            'requires_follow_up' => 'no',
            'next_follow_up_date' => date('Y-m-d', strtotime('+14 days')),
        ]);
        $this->followUpIds[] = $noId;
        $no = (new FollowUpService())->find($noId);
        $this->assertSame('no', $no['requires_follow_up'], 'Seguimiento No queda marcado');
        $this->assertTrue(
            $no['next_follow_up_date'] === null || $no['next_follow_up_date'] === '',
            'Seguimiento No deja la próxima fecha en NULL'
        );
    }

    private function testPersonHistoryDossier(): void
    {
        $validator = new FollowUpSearchValidator();
        $this->assertTrue(isset($validator->validate([])['rut']), 'Búsqueda exige RUT o nombre');
        $this->assertTrue(isset($validator->validate(['rut' => '123'])['rut']), 'Búsqueda rechaza RUT inválido');
        $this->assertSame([], $validator->validate(['name' => 'Ana']), 'Búsqueda por nombre no exige RUT');

        $pdo = Database::connection();
        $before = (int) $pdo->query('SELECT COUNT(*) FROM senda_people')->fetchColumn();
        $missing = (new PersonService())->lookup($this->unusedRut());
        $after = (int) $pdo->query('SELECT COUNT(*) FROM senda_people')->fetchColumn();
        $this->assertFalse(!empty($missing['exists']), 'RUT inexistente no encuentra persona');
        $this->assertSame($before, $after, 'Buscar por RUT no crea persona');

        $personId = $this->personIds[0] ?? $this->ensurePerson();
        $dossier = (new HistoryService())->dossier($personId);
        $this->assertSame($personId, (int) $dossier['person']['id'], 'El dossier corresponde a la persona');
        $this->assertTrue((int) $dossier['metrics']['attentions_count'] >= 1, 'El dossier cuenta atenciones');

        $types = array_column($dossier['timeline'], 'type');
        $this->assertTrue(in_array('attention', $types, true), 'El historial incluye atenciones');
        $this->assertTrue(in_array('followup', $types, true), 'El historial incluye seguimientos');

        $tabaco = null;
        foreach ($dossier['referrals'] as $referral) {
            if (empty($referral['screening_used'])) {
                continue;
            }

            foreach ($referral['assist'] as $row) {
                if (($row['key'] ?? '') === 'tabaco' && ($row['score'] ?? '') !== '') {
                    $tabaco = $row;
                    break 2;
                }
            }
        }

        $this->assertTrue(is_array($tabaco), 'El dossier expone ASSIST almacenado');
        $this->assertSame('21', (string) ($tabaco['score'] ?? ''), 'ASSIST histórico conserva el puntaje');
        $this->assertSame(
            AssistClassificationService::TRATAMIENTO,
            (string) ($tabaco['risk_level'] ?? ''),
            'ASSIST histórico usa el nivel almacenado'
        );
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function referralValidatorBase(array $extra = []): array
    {
        return $extra + [
            'senda_attention_id' => '1',
            'request_date' => date('Y-m-d'),
            'demand_origin' => ReferralInstitutionType::CENTRO_SALUD,
            'receiving_officer' => 'Funcionario de prueba',
            'applicant_kind' => 'persona_implicada',
            'enrolled_health_center' => 'no',
            'has_previous_treatments' => 'no',
            'screening_used' => 'no',
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function referralStorePayload(int $attentionId, array $extra): array
    {
        return array_merge([
            'senda_attention_id' => (string) $attentionId,
            'request_date' => date('Y-m-d'),
            'demand_origin' => ReferralInstitutionType::CENTRO_SALUD,
            'receiving_officer' => 'Funcionario de prueba SENDA',
            'applicant_kind' => 'persona_implicada',
            'enrolled_health_center' => 'no',
            'has_previous_treatments' => 'no',
            'screening_used' => 'no',
        ], $extra);
    }

    private function createAttention(string $entryType): int
    {
        $personId = $this->ensurePerson();
        $data = [
            'entry_type' => $entryType,
            'senda_person_id' => (string) $personId,
            'attention_date' => date('Y-m-d'),
            'attention_time' => date('H:i'),
            'summary' => 'Atención de prueba funcional',
        ];

        if ($entryType === EntryType::DERIVACION) {
            $data['referral_institution_type'] = ReferralInstitutionType::CENTRO_SALUD;
            $data['referral_institution_name'] = 'CESFAM Centro';
            $data['referral_person'] = 'Dra. Pérez';
        }

        $id = (new AttentionService())->create($data);
        $this->attentionIds[] = $id;

        return $id;
    }

    private function ensurePerson(): int
    {
        if ($this->personIds !== []) {
            return $this->personIds[0];
        }

        $rut = $this->unusedRut();
        $id = (new PersonService())->create([
            'first_names' => 'Ana Prueba',
            'paternal_surname' => 'Funcional',
            'maternal_surname' => 'Senda',
            'rut' => ChileanRutValidator::format($rut) ?? $rut,
            'birth_date' => '1990-05-12',
        ]);
        $this->personIds[] = $id;

        return $id;
    }

    private function unusedRut(): string
    {
        $people = new PersonService();

        for ($body = 88000000; $body <= 88999999; $body++) {
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

        $rest = 11 - ($sum % 11);

        return match ($rest) {
            11 => '0',
            10 => 'K',
            default => (string) $rest,
        };
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach (array_reverse($this->followUpIds) as $id) {
            $pdo->prepare('DELETE FROM senda_follow_ups WHERE id = :id')->execute(['id' => $id]);
        }

        foreach (array_reverse($this->referralIds) as $id) {
            $pdo->prepare('DELETE FROM senda_assist_results WHERE assisted_referral_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM senda_assisted_referrals WHERE id = :id')->execute(['id' => $id]);
        }

        foreach (array_reverse($this->attentionIds) as $id) {
            $pdo->prepare('DELETE FROM senda_follow_ups WHERE senda_attention_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM senda_attentions WHERE id = :id')->execute(['id' => $id]);
        }

        foreach (array_reverse($this->personIds) as $id) {
            $pdo->prepare('DELETE FROM senda_people WHERE id = :id')->execute(['id' => $id]);
        }

        $leftoverUsers = $pdo->query(
            "SELECT id FROM users WHERE email LIKE 'senda.func.test.%@municipalidad.local'"
        )->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        $userIds = array_values(array_unique(array_merge($this->userIds, array_map('intval', $leftoverUsers))));

        foreach ($userIds as $id) {
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $id]);
            try {
                $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
            } catch (\PDOException) {
                $pdo->prepare(
                    'UPDATE users SET is_active = 0 WHERE id = :id'
                )->execute(['id' => $id]);
            }
        }
    }

    private function assertTrue(bool $condition, string $label): void
    {
        if ($condition) {
            $this->pass($label);
            return;
        }

        $this->fail($label, 'se esperaba verdadero');
    }

    private function assertFalse(bool $condition, string $label): void
    {
        $this->assertTrue(!$condition, $label);
    }

    private function assertSame(mixed $expected, mixed $actual, string $label): void
    {
        if ($expected === $actual) {
            $this->pass($label);
            return;
        }

        $this->fail($label, 'esperado ' . var_export($expected, true) . ' / obtenido ' . var_export($actual, true));
    }

    private function pass(string $label): void
    {
        $this->passed++;
        echo '  PASS  ' . $label . PHP_EOL;
    }

    private function fail(string $label, string $detail): void
    {
        $this->failed++;
        $this->failures[] = $label . ' — ' . $detail;
        echo '  FAIL  ' . $label . ' — ' . $detail . PHP_EOL;
    }

    private function printSummary(): void
    {
        $total = $this->passed + $this->failed;
        echo PHP_EOL . "Resultado: {$this->passed}/{$total} pruebas OK";
        if ($this->failed > 0) {
            echo ", {$this->failed} fallidas";
        }
        echo PHP_EOL;

        foreach ($this->failures as $failure) {
            echo '  - ' . $failure . PHP_EOL;
        }
    }
}

exit((new SendaFunctionalTests())->run());
