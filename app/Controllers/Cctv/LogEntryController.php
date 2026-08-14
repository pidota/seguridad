<?php

declare(strict_types=1);

namespace App\Controllers\Cctv;

use App\Controllers\Camera\CameraController as CctvLayoutController;
use App\Services\Cctv\CameraCatalog;
use App\Services\Cctv\CameraService;
use App\Services\Cctv\CatalogService;
use App\Services\Cctv\LogContactCatalog;
use App\Services\Cctv\LogEntryCatalog;
use App\Services\Cctv\PoliceArrivalCatalog;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\ShiftService;
use App\Services\Cctv\TechnicalEntryCatalog;
use App\Validators\Cctv\IncidentStoreValidator;
use App\Validators\Cctv\LogEntryStoreValidator;
use App\Validators\Cctv\TechnicalStoreValidator;
use Core\Auth;
use Core\Exceptions\HttpException;
use Core\Request;
use Core\Session;

final class LogEntryController extends CctvLayoutController
{
    public function __construct(
        private readonly LogEntryService $entries = new LogEntryService(),
        private readonly ShiftService $shifts = new ShiftService(),
        private readonly CatalogService $catalogs = new CatalogService(),
        private readonly CameraService $cameras = new CameraService()
    ) {
    }

    public function create(): void
    {
        if (!hasPermission('cctv.log.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede registrar novedades.');
            $this->redirect(url('/cctv'));
        }

        $openShift = $this->requireOpenShiftOrRedirect('novedad');
        $user = Auth::user();

        $this->cameraView('log/create', [
            'title' => 'Registrar Novedad',
            'record' => $this->entries->defaults(),
            'openShift' => $openShift,
            'operatorName' => trim((string) ($user['name'] ?? '')),
            'logTypes' => array_values(array_filter(
                $this->catalogs->logTypeOptions(),
                static fn (array $row): bool => !in_array(
                    (string) ($row['value'] ?? ''),
                    ['incidente', 'novedad_tecnica'],
                    true
                )
            )),
            'cameras' => $this->cameras->activeOptions(),
            'sectors' => $this->cameras->sectorOptions(),
            'moduleScripts' => $this->cctvScripts('log.js', 'unsaved.js'),
        ]);
    }

    public function store(Request $request): void
    {
        if (!hasPermission('cctv.log.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede registrar novedades.');
            $this->redirect(url('/cctv'));
        }

        $this->requireOpenShiftOrRedirect('novedad');

        $payload = $request->all();
        $logTypeIds = array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $this->catalogs->activeLogTypes()
        );
        $errors = (new LogEntryStoreValidator($logTypeIds))->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/cctv/log/create'));
        }

        try {
            $this->entries->createForOpenShift($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/cctv/log/create'));
        }

        Session::flashAlert('success', 'Novedad registrada', 'Registro agregado a la bitácora correctamente.');
        $this->redirect(url('/cctv#bitacora-turno'));
    }

    public function createIncident(): void
    {
        if (!hasPermission('cctv.log.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede registrar incidentes.');
            $this->redirect(url('/cctv'));
        }

        $openShift = $this->requireOpenShiftOrRedirect('incidente');
        $user = Auth::user();
        $incidentTypes = array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'value' => (string) ($row['value'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'allows_other' => !empty($row['allows_other']),
        ], $this->catalogs->incidentTypeOptions());

        $this->cameraView('log/incident', [
            'title' => 'Registrar Incidente',
            'record' => $this->entries->incidentDefaults(),
            'openShift' => $openShift,
            'operatorName' => trim((string) ($user['name'] ?? '')),
            'incidentTypes' => $incidentTypes,
            'statuses' => LogEntryCatalog::statuses(),
            'contactTypes' => LogContactCatalog::types(),
            'policeArrivalOptions' => PoliceArrivalCatalog::options(),
            'cameras' => $this->cameras->activeOptions(),
            'sectors' => $this->cameras->sectorOptions(),
            'moduleScripts' => $this->cctvScripts('incident.js', 'unsaved.js'),
        ]);
    }

    public function storeIncident(Request $request): void
    {
        if (!hasPermission('cctv.log.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede registrar incidentes.');
            $this->redirect(url('/cctv'));
        }

        $this->requireOpenShiftOrRedirect('incidente');

        $payload = $request->all();
        $incidentTypes = array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'allows_other' => !empty($row['allows_other']),
        ], $this->catalogs->incidentTypeOptions());
        $errors = (new IncidentStoreValidator($incidentTypes))->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/cctv/log/incident/create'));
        }

        try {
            $this->entries->createIncidentForOpenShift($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/cctv/log/incident/create'));
        }

        Session::flashAlert('success', 'Incidente registrado', 'El incidente quedó agregado a la bitácora del turno.');
        $this->redirect(url('/cctv#bitacora-turno'));
    }

    public function createTechnical(): void
    {
        if (!hasPermission('cctv.log.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede registrar novedades técnicas.');
            $this->redirect(url('/cctv'));
        }

        $openShift = $this->requireOpenShiftOrRedirect('tecnica');
        $user = Auth::user();
        $technicalIssueTypes = array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'value' => (string) ($row['value'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'allows_other' => !empty($row['allows_other']),
        ], $this->catalogs->technicalIssueTypeOptions());

        $this->cameraView('log/technical', [
            'title' => 'Registrar Novedad Técnica',
            'record' => $this->entries->technicalDefaults(),
            'openShift' => $openShift,
            'operatorName' => trim((string) ($user['name'] ?? '')),
            'technicalIssueTypes' => $technicalIssueTypes,
            'statuses' => TechnicalEntryCatalog::statuses(),
            'cameraStatuses' => CameraCatalog::statuses(),
            'cameras' => $this->cameras->monitoringOptions(),
            'equipment' => $this->catalogs->equipmentOptions(),
            'moduleScripts' => $this->cctvScripts('log.js'),
        ]);
    }

    public function storeTechnical(Request $request): void
    {
        if (!hasPermission('cctv.log.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede registrar novedades técnicas.');
            $this->redirect(url('/cctv'));
        }

        $this->requireOpenShiftOrRedirect('tecnica');

        $payload = $request->all();
        $technicalIssueTypes = array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'allows_other' => !empty($row['allows_other']),
        ], $this->catalogs->technicalIssueTypeOptions());
        $errors = (new TechnicalStoreValidator($technicalIssueTypes))->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/cctv/log/technical/create'));
        }

        try {
            $this->entries->createTechnicalForOpenShift($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/cctv/log/technical/create'));
        }

        Session::flashAlert('success', 'Novedad técnica registrada', 'La falla quedó registrada en la bitácora del turno.');
        $this->redirect(url('/cctv#bitacora-turno'));
    }

    public function show(Request $request, string $id): void
    {
        if (!hasPermission('cctv.log.view')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede consultar la bitácora.');
            $this->redirect(url('/cctv/log'));
        }

        try {
            $record = $this->entries->detailForView((int) $id, Auth::id());
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/cctv/log'));
        }

        $this->cameraView('log/show', [
            'title' => 'Detalle de registro CCTV',
            'record' => $record,
            'canEditLogEntry' => cctv_can_edit_log_entry($record),
            'canCancelLogEntry' => cctv_can_cancel_log_entry($record),
        ]);
    }

    public function destroy(Request $request, string $id): void
    {
        if (!hasPermission('cctv.log.delete')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede anular registros de bitácora.');
            $this->redirect(url('/cctv/log'));
        }

        try {
            $this->entries->cancel((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/cctv/log/' . $id));
        }

        Session::flashAlert(
            'success',
            'Registro anulado',
            'El registro dejó de aparecer en la bitácora operativa. La acción quedó registrada en auditoría.'
        );
        $this->redirect(url('/cctv/log'));
    }

    /**
     * @return array<string, mixed>
     */
    private function requireOpenShiftOrRedirect(string $kind): array
    {
        $operatorId = Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            Session::flashAlert('warning', 'Sesión inválida', 'Debe iniciar sesión para continuar.');
            $this->redirect(url('/cctv'));
        }

        $openShift = $this->shifts->findOpenForOperator($operatorId);
        if ($openShift === null) {
            Session::flashAlert('warning', 'Turno requerido', $this->openShiftRequiredMessage($kind));
            $this->redirect(url('/cctv'));
        }

        return $openShift;
    }

    private function openShiftRequiredMessage(string $kind): string
    {
        return match ($kind) {
            'incidente' => 'Debe iniciar un turno antes de registrar un incidente.',
            'tecnica' => 'Debe iniciar un turno antes de registrar una novedad técnica.',
            default => 'Debe iniciar un turno antes de registrar una novedad.',
        };
    }

    private function failAndRedirect(\Throwable $e, string $to): never
    {
        if ($e instanceof HttpException && in_array($e->getStatusCode(), [403, 404, 422], true)) {
            $redirectTo = $to;
            $tone = $e->getStatusCode() === 403 ? 'warning' : 'error';
            $title = 'No se pudo completar la acción';

            if ($e->getStatusCode() === 422 && $this->isOpenShiftRequiredError($e)) {
                $redirectTo = url('/cctv');
                $tone = 'warning';
                $title = 'Turno requerido';
            }

            Session::flashAlert($tone, $title, $e->getMessage());
            $this->redirect($redirectTo);
        }

        throw $e;
    }

    private function isOpenShiftRequiredError(HttpException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'iniciar un turno')
            || str_contains($message, 'turno abierto')
            || str_contains($message, 'turno ya no está abierto');
    }
}
