<?php



declare(strict_types=1);



namespace App\Controllers\Camera;



use App\Services\Camera\EventCatalog;

use App\Services\Camera\EventService;

use App\Repositories\Cctv\LogEntryRepository;

use App\Services\Cctv\CatalogService;

use App\Services\Cctv\CameraService;

use App\Services\Cctv\LogContactCatalog;

use App\Services\Cctv\LogEntryCatalog;

use App\Services\Cctv\LogEntryService;

use App\Services\Cctv\PoliceArrivalCatalog;

use App\Services\Cctv\ShiftService;

use App\Validators\Cctv\IncidentStoreValidator;
use App\Validators\Cctv\LogEntryStoreValidator;
use App\Validators\Cctv\TechnicalStoreValidator;
use App\Validators\Camera\EventUpdateValidator;
use App\Models\Cctv\LogType;

use Core\Auth;

use Core\Exceptions\HttpException;

use Core\Request;

use Core\Session;



final class EventController extends CameraController

{

    public function __construct(

        private readonly LogEntryService $logEntries = new LogEntryService(),

        private readonly EventService $events = new EventService(),

        private readonly CatalogService $catalogs = new CatalogService(),

        private readonly CameraService $cameras = new CameraService(),

        private readonly ShiftService $shifts = new ShiftService()

    ) {

    }



    public function index(Request $request): void

    {

        if (!hasPermission('cctv.log.view')) {

            Session::flashAlert('warning', 'Acceso denegado', 'No puede consultar la bitácora.');

            $this->redirect(url('/cctv'));

        }



        $filters = $this->listFilters($request);

        $page = max(1, (int) $request->query('page', 1));

        $result = $this->logEntries->searchHistory($filters, $page);



        $this->cameraView('events/index', [

            'title' => 'Bitácora histórica',

            'entries' => $result['data'],

            'total' => $result['total'],

            'page' => $result['page'],

            'pages' => $result['pages'],

            'filters' => $filters,

            'logTypes' => $this->catalogs->logTypeOptions(),

            'incidentTypes' => $this->catalogs->incidentTypeOptions(),

            'sectors' => $this->cameras->sectorOptions(),

            'cameras' => $this->cameras->activeOptions(),

            'contactTypes' => LogContactCatalog::types(),

            'statuses' => LogEntryCatalog::filterStatuses(),

            'operators' => hasPermission('cctv.log.view_all') ? $this->logEntries->operatorOptions() : [],

            'canViewAll' => hasPermission('cctv.log.view_all'),

            'policeOptions' => PoliceArrivalCatalog::options(),

            'canRegisterEntry' => Auth::id() !== null && $this->shifts->findOpenForOperator((int) Auth::id()) !== null,

        ]);

    }



    public function show(Request $request, string $id): void

    {

        try {

            $record = $this->events->find((int) $id);

        } catch (\Throwable $e) {

            $this->failAndRedirect($e, url('/cctv/log'));

        }



        $this->cameraView('events/show', [

            'title' => 'Ver novedad — CCTV',

            'record' => $record,

        ]);

    }



    public function edit(Request $request, string $id): void
    {
        $entryId = (int) $id;
        $logEntry = (new LogEntryRepository())->findById($entryId);

        if ($logEntry !== null) {
            try {
                $record = $this->logEntries->recordForEdit($entryId);
            } catch (\Throwable $e) {
                $this->failAndRedirect($e, url('/cctv/log'));
            }

            if (!cctv_can_edit_log_entry($record)) {
                Session::flashAlert(
                    'warning',
                    'Edición restringida',
                    'No puede modificar este registro de bitácora.'
                );
                $this->redirect(url('/cctv/log/' . $entryId));
            }

            $this->renderLogEntryEditForm($record);

            return;
        }

        try {
            $record = $this->events->find($entryId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/cctv/log'));
        }

        if (!hasPermission('cctv.log.view_all') && (int) ($record['created_by'] ?? 0) !== (int) Auth::id()) {
            Session::flashAlert('warning', 'Edición restringida', 'No puede modificar registros de otros operadores.');
            $this->redirect(url('/cctv/log'));
        }

        $this->cameraView('events/form', [
            'title' => 'Editar novedad — CCTV',
            'record' => $record,
            'shifts' => EventCatalog::shifts(),
            'logTypes' => $this->catalogs->logTypeOptions(),
            'classifications' => $this->catalogs->incidentTypeOptions(),
            'moduleScripts' => $this->cctvScripts('log.js'),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $entryId = (int) $id;

        if ((new LogEntryRepository())->findById($entryId) !== null) {
            $editUrl = url('/cctv/log/' . $entryId . '/edit');

            try {
                $record = $this->logEntries->find($entryId);
            } catch (\Throwable $e) {
                $this->failAndRedirect($e, url('/cctv/log'));
            }

            if (!cctv_can_edit_log_entry($record)) {
                Session::flashAlert('warning', 'Edición restringida', 'No puede modificar este registro de bitácora.');
                $this->redirect(url('/cctv/log/' . $entryId));
            }

            $payload = $request->all();
            $errors = $this->validateLogEntryUpdate($record, $payload);

            if ($errors !== []) {
                Session::flashInput($payload);
                Session::flashErrors($errors);
                Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
                $this->redirect($editUrl);
            }

            try {
                $this->logEntries->update($entryId, $payload);
            } catch (\Throwable $e) {
                Session::flashInput($payload);
                $this->failAndRedirect($e, $editUrl);
            }

            Session::flashAlert('success', 'Registro actualizado', 'Los cambios quedaron registrados en auditoría.');
            $this->redirect(url('/cctv/log/' . $entryId));

            return;
        }

        try {
            $record = $this->events->find($entryId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/cctv/log'));
        }

        $payload = $request->all();
        $editUrl = url('/cctv/log/' . $id . '/edit');
        $errors = (new EventUpdateValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect($editUrl);
        }

        try {
            $this->events->update((int) $id, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, $editUrl);
        }

        Session::flashAlert('success', 'Novedad actualizada', 'Los cambios quedaron registrados en auditoría.');
        $this->redirect(url('/cctv/log/' . $id));
    }

    /**
     * @param array<string, mixed> $record
     */
    private function renderLogEntryEditForm(array $record): void
    {
        $entryId = (int) ($record['id'] ?? 0);
        $shiftId = (int) ($record['shift_id'] ?? 0);
        $shift = $shiftId > 0 ? $this->shifts->find($shiftId) : null;
        $user = Auth::user();
        $slug = (string) ($record['log_type_slug'] ?? '');
        $common = [
            'record' => $record,
            'isEdit' => true,
            'openShift' => $shift ?? [],
            'operatorName' => trim((string) ($user['name'] ?? '')),
            'formAction' => url('/cctv/log/' . $entryId),
            'cancelUrl' => url('/cctv/log/' . $entryId),
            'cameras' => $this->cameras->activeOptions(),
            'sectors' => $this->cameras->sectorOptions(),
        ];

        if ($slug === LogType::SLUG_INCIDENT) {
            $incidentTypes = array_map(static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'value' => (string) ($row['value'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'allows_other' => !empty($row['allows_other']),
            ], $this->catalogs->incidentTypeOptions());

            $this->cameraView('log/incident', array_merge($common, [
                'title' => 'Editar incidente',
                'incidentTypes' => $incidentTypes,
                'statuses' => LogEntryCatalog::statuses(),
                'contactTypes' => LogContactCatalog::types(),
                'policeArrivalOptions' => PoliceArrivalCatalog::options(),
                'moduleScripts' => $this->cctvScripts('incident.js', 'unsaved.js'),
            ]));

            return;
        }

        if ($slug === LogType::SLUG_TECHNICAL) {
            $technicalIssueTypes = array_map(static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'value' => (string) ($row['value'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'allows_other' => !empty($row['allows_other']),
            ], $this->catalogs->technicalIssueTypeOptions());

            $this->cameraView('log/technical', array_merge($common, [
                'title' => 'Editar novedad técnica',
                'technicalIssueTypes' => $technicalIssueTypes,
                'statuses' => \App\Services\Cctv\TechnicalEntryCatalog::statuses(),
                'cameraStatuses' => \App\Services\Cctv\CameraCatalog::statuses(),
                'cameras' => $this->cameras->monitoringOptions(),
                'equipment' => $this->catalogs->equipmentOptions(),
                'moduleScripts' => $this->cctvScripts('log.js'),
            ]));

            return;
        }

        $this->cameraView('log/create', array_merge($common, [
            'title' => 'Editar novedad',
            'logTypes' => array_values(array_filter(
                $this->catalogs->logTypeOptions(),
                static fn (array $row): bool => !in_array(
                    (string) ($row['value'] ?? ''),
                    ['incidente', 'novedad_tecnica'],
                    true
                )
            )),
            'moduleScripts' => $this->cctvScripts('log.js', 'unsaved.js'),
        ]));
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function validateLogEntryUpdate(array $record, array $payload): array
    {
        $slug = (string) ($record['log_type_slug'] ?? '');

        if ($slug === LogType::SLUG_INCIDENT) {
            $incidentTypes = array_map(static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'allows_other' => !empty($row['allows_other']),
            ], $this->catalogs->incidentTypeOptions());

            return (new IncidentStoreValidator($incidentTypes))->validate($payload);
        }

        if ($slug === LogType::SLUG_TECHNICAL) {
            $technicalIssueTypes = array_map(static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'allows_other' => !empty($row['allows_other']),
            ], $this->catalogs->technicalIssueTypeOptions());

            return (new TechnicalStoreValidator($technicalIssueTypes))->validate($payload);
        }

        $logTypeIds = array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $this->catalogs->activeLogTypes()
        );

        return (new LogEntryStoreValidator($logTypeIds))->validate($payload);
    }

    /**
     * @return array<string, string>
     */
    private function listFilters(Request $request): array

    {

        $dateFrom = trim((string) $request->query('date_from', ''));

        $dateTo = trim((string) $request->query('date_to', ''));

        $logType = trim((string) $request->query('log_type', ''));

        $incidentType = trim((string) $request->query('incident_type', ''));

        $sectorId = trim((string) $request->query('sector_id', ''));

        $cameraId = trim((string) $request->query('camera_id', ''));

        $contactType = trim((string) $request->query('contact_type', ''));

        $status = trim((string) $request->query('status', ''));

        $policeArrived = trim((string) $request->query('police_arrived', ''));

        $coordinationNotified = trim((string) $request->query('coordination_notified', ''));
        if (!in_array($coordinationNotified, ['', '0', '1'], true)) {
            $coordinationNotified = '';
        }

        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) > 200) {
            $query = mb_substr($query, 0, 200);
        }



        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) {

            $dateFrom = '';

        }



        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1) {

            $dateTo = '';

        }



        if ($logType !== '' && !$this->catalogs->isValidLogTypeSlug($logType)) {

            $logType = '';

        }



        if ($incidentType !== '' && !$this->catalogs->isValidIncidentTypeSlug($incidentType)) {

            $incidentType = '';

        }



        if ($sectorId !== '' && (int) $sectorId < 1) {

            $sectorId = '';

        }



        if ($cameraId !== '' && (int) $cameraId < 1) {

            $cameraId = '';

        }



        if ($contactType !== '' && !LogContactCatalog::isValidType($contactType)) {

            $contactType = '';

        }



        if ($status !== '' && !LogEntryCatalog::isValidFilterStatus($status)) {

            $status = '';

        }



        if ($policeArrived !== '' && !in_array($policeArrived, PoliceArrivalCatalog::values(), true)) {

            $policeArrived = '';

        }



        $createdBy = '';

        if (hasPermission('cctv.log.view_all')) {

            $createdBy = trim((string) $request->query('created_by', ''));

            if ($createdBy !== '' && (int) $createdBy < 1) {

                $createdBy = '';

            }

        } elseif (Auth::id() !== null) {

            $createdBy = (string) Auth::id();

        }



        return [

            'q' => $query,

            'date_from' => $dateFrom,

            'date_to' => $dateTo,

            'created_by' => $createdBy,

            'log_type' => $logType,

            'incident_type' => $incidentType,

            'sector_id' => $sectorId,

            'camera_id' => $cameraId,

            'contact_type' => $contactType,

            'status' => $status,

            'police_arrived' => $policeArrived,

            'coordination_notified' => $coordinationNotified,

        ];

    }



    private function failAndRedirect(\Throwable $e, string $to): never

    {

        if ($e instanceof HttpException && in_array($e->getStatusCode(), [403, 404, 422], true)) {

            Session::flashAlert(

                $e->getStatusCode() === 403 ? 'warning' : 'error',

                'No se pudo completar la acción',

                $e->getMessage()

            );

            $this->redirect($to);

        }



        throw $e;

    }

}

