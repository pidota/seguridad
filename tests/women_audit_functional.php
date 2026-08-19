<?php

declare(strict_types=1);

/**
 * Pruebas de auditoría del módulo Oficina de la Mujer.
 * Ejecutar: php tests/women_audit_functional.php
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

use App\Repositories\AuditRepository;
use App\Repositories\WomenOffice\CatalogRepository;
use App\Services\AuditService;
use App\Services\WomenOffice\PersonService;
use App\Services\WomenOffice\WomenAuditService;
use App\Services\WomenOffice\WomenCaseService;
use App\Support\ChileanRutValidator;
use Core\Auth;
use Core\Database;
use Core\Permission;
use Core\Request;
use Core\Session;

final class WomenAuditFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<int> */
    private array $caseIds = [];

    /** @var list<int> */
    private array $personIds = [];

    private int $adminId = 0;

    public function run(): int
    {
        $this->boot();

        try {
            $this->testCaseCreateAudit();
            $this->testCaseUpdateSanitizesText();
            $this->testViewCaseAuditWithSection();
            $this->testAggressorAuditOmitsPii();
        } finally {
            $this->cleanup();
        }

        echo PHP_EOL . 'Resultado auditoría Women: ' . $this->passed . '/' . ($this->passed + $this->failed) . ' pruebas OK' . PHP_EOL;

        return $this->failed === 0 ? 0 : 1;
    }

    private function boot(): void
    {
        Session::start();
        Request::capture();

        $pdo = Database::connection();
        $this->adminId = (int) $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'superadministrador' AND u.is_active = 1
             LIMIT 1"
        )->fetchColumn();

        if ($this->adminId < 1) {
            throw new RuntimeException('No hay un superadministrador activo para las pruebas.');
        }

        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();
    }

    private function testCaseCreateAudit(): void
    {
        $caseId = $this->createCase();
        $payload = $this->latestAuditPayload(
            AuditService::RESOURCE_WOMEN_CASE,
            (string) $caseId,
            AuditService::ACTION_CREATED
        );

        $this->check('Creación de caso queda auditada', ($payload['case_number'] ?? '') !== '');
        $this->check('Auditoría de creación no guarda texto completo del hecho', !isset($payload['description']));
    }

    private function testCaseUpdateSanitizesText(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCase();

        $physicalId = 0;
        foreach ($catalogs->violenceTypes() as $type) {
            if (($type['slug'] ?? '') === 'fisica') {
                $physicalId = (int) $type['id'];
            }
        }

        $longDescription = str_repeat('Relato confidencial de prueba. ', 30);
        $cases->updateFacts($caseId, [
            'incident_date_precision' => 'exact',
            'incident_date' => date('Y-m-d'),
            'description' => $longDescription,
            'violence_type_ids' => array_values(array_filter([$physicalId])),
        ]);

        $row = $this->latestAuditRow(
            AuditService::RESOURCE_WOMEN_CASE,
            (string) $caseId,
            AuditService::ACTION_UPDATED
        );
        $newValues = json_decode((string) ($row['new_values'] ?? ''), true) ?: [];

        $this->check('Actualización auditada usa extracto del relato', isset($newValues['description_excerpt']));
        $this->check('Actualización no guarda relato completo', !isset($newValues['description']));
        $this->check('Extracto respeta límite de caracteres', mb_strlen((string) ($newValues['description_excerpt'] ?? '')) <= 120);
    }

    private function testViewCaseAuditWithSection(): void
    {
        $caseId = $this->createCase();
        $audit = new WomenAuditService();
        $cases = new WomenCaseService();
        $case = $cases->findDetailed($caseId);

        $audit->viewedCase($caseId, (string) ($case['case_number'] ?? ''), 'facts');

        $row = $this->latestAuditRow(
            AuditService::RESOURCE_WOMEN_CASE,
            (string) $caseId,
            AuditService::ACTION_VIEW_CASE
        );
        $newValues = json_decode((string) ($row['new_values'] ?? ''), true) ?: [];

        $this->check('Consulta de caso queda auditada', ($row['action'] ?? '') === AuditService::ACTION_VIEW_CASE);
        $this->check('Consulta auditada incluye sección', ($newValues['section'] ?? '') === 'facts');
        $this->check('Consulta auditada incluye número de caso', ($newValues['case_number'] ?? '') !== '');
    }

    private function testAggressorAuditOmitsPii(): void
    {
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();
        $caseId = $this->createCase();

        $parejaId = 0;
        foreach ($catalogs->relationshipTypes() as $type) {
            if (($type['slug'] ?? '') === 'pareja') {
                $parejaId = (int) $type['id'];
            }
        }

        $cases->updateAggressor($caseId, [
            'relationship_type_id' => $parejaId,
            'current_relationship' => 'yes',
            'aggressor_first_names' => 'Pedro',
            'aggressor_paternal_surname' => 'Denunciado',
            'aggressor_rut' => '11.111.111-1',
            'aggressor_phone' => '912345678',
        ]);

        $row = $this->latestAuditRow(
            AuditService::RESOURCE_WOMEN_CASE,
            (string) $caseId,
            AuditService::ACTION_UPDATED
        );
        $encoded = (string) ($row['new_values'] ?? '');

        $this->check('Auditoría de denunciado omite nombre', !str_contains($encoded, 'Pedro'));
        $this->check('Auditoría de denunciado omite teléfono', !str_contains($encoded, '912345678'));
    }

    private function createCase(): int
    {
        $people = new PersonService();
        $cases = new WomenCaseService();
        $catalogs = new CatalogRepository();

        $body = random_int(15000000, 19999999);
        $rut = ChileanRutValidator::format((string) $body . $this->verifier((string) $body)) ?? (string) $body;

        $personId = $people->create([
            'first_names' => 'Ana',
            'paternal_surname' => 'Auditoria',
            'maternal_surname' => 'Prueba',
            'rut' => $rut,
            'birth_date' => '1992-05-10',
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
            'reported_time' => '10:00',
            'report_channel_id' => $channelId,
        ]);
        $this->caseIds[] = $caseId;

        return $caseId;
    }

    /**
     * @return array<string, mixed>
     */
    private function latestAuditPayload(string $resource, string $resourceId, string $action): array
    {
        $row = $this->latestAuditRow($resource, $resourceId, $action);

        return json_decode((string) ($row['new_values'] ?? ''), true) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestAuditRow(string $resource, string $resourceId, string $action): array
    {
        $rows = (new AuditRepository())->forResource(
            AuditService::MODULE_WOMEN,
            $resource,
            $resourceId,
            20
        );

        foreach ($rows as $row) {
            if (($row['action'] ?? '') === $action) {
                return $row;
            }
        }

        return [];
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
            $pdo->prepare('DELETE FROM women_case_violence_types WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_case_aggressors WHERE case_id = :id')->execute(['id' => $caseId]);
            $pdo->prepare('DELETE FROM women_cases WHERE id = :id')->execute(['id' => $caseId]);
        }

        foreach ($this->personIds as $personId) {
            $pdo->prepare('DELETE FROM women_people WHERE id = :id')->execute(['id' => $personId]);
        }
    }
}

exit((new WomenAuditFunctionalTests())->run());
