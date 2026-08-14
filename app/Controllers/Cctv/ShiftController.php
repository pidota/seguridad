<?php

declare(strict_types=1);

namespace App\Controllers\Cctv;

use App\Controllers\Camera\CameraController as CctvLayoutController;
use App\Exceptions\Cctv\OpenShiftAlreadyExistsException;
use App\Services\Cctv\EquipmentCheckCatalog;
use App\Services\Cctv\EquipmentService;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\ShiftService;
use App\Validators\Cctv\ShiftReceptionValidator;
use Core\Auth;
use Core\Exceptions\HttpException;
use Core\Request;
use Core\Session;

final class ShiftController extends CctvLayoutController
{
    public function __construct(
        private readonly ShiftService $shifts = new ShiftService(),
        private readonly EquipmentService $equipment = new EquipmentService()
    ) {
    }

    public function index(Request $request): void
    {
        if (!hasPermission('cctv.shifts.view')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede consultar turnos.');
            $this->redirect(url('/cctv'));
        }

        $filters = $this->listFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->shifts->searchHistory($filters, $page);

        $this->cameraView('shifts/index', [
            'title' => 'Historial de turnos',
            'shifts' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'statuses' => $this->statusFilterOptions(),
            'operators' => hasPermission('cctv.shifts.view_all') ? $this->shifts->operatorOptions() : [],
            'canViewAll' => hasPermission('cctv.shifts.view_all'),
            'canViewLog' => hasPermission('cctv.log.view'),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        if (!hasPermission('cctv.shifts.view')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede consultar turnos.');
            $this->redirect(url('/cctv'));
        }

        $shiftId = (int) $id;
        if ($shiftId < 1) {
            Session::flashAlert('error', 'Turno no encontrado', 'El identificador del turno no es válido.');
            $this->redirect(url('/cctv/shifts'));
        }

        $logOrder = strtolower((string) $request->query('log_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        try {
            $detail = $this->shifts->detailForView($shiftId, Auth::id(), $logOrder);
        } catch (HttpException $e) {
            Session::flashAlert(
                $e->getStatusCode() === 403 ? 'warning' : 'error',
                'No se pudo consultar el turno',
                $e->getMessage()
            );
            $this->redirect(url('/cctv/shifts'));
        }

        $shift = $detail['shift'];

        $this->cameraView('shifts/show', [
            'title' => 'Detalle de turno CCTV',
            'shift' => $shift,
            'stats' => $detail['stats'],
            'openingChecks' => $detail['opening_checks'],
            'closingChecks' => $detail['closing_checks'],
            'shiftTimeline' => $detail['timeline'],
            'incidents' => $detail['incidents'],
            'technicalIssues' => $detail['technical_issues'],
            'coordinations' => $detail['coordinations'],
            'canViewLog' => hasPermission('cctv.log.view'),
            'logOrderOptions' => LogEntryService::timelineOrderOptions(),
        ]);
    }

    public function create(): void
    {
        if (!hasPermission('cctv.shifts.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede iniciar turnos.');
            $this->redirect(url('/cctv'));
        }

        $operatorId = Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            Session::flashAlert('warning', 'Sesión inválida', 'Debe iniciar sesión para abrir un turno.');
            $this->redirect(url('/cctv'));
        }

        if ($this->shifts->findOpenForOperator($operatorId) !== null) {
            Session::flashAlert('warning', 'Turno CCTV', 'Ya posee un turno CCTV abierto.');
            $this->redirect(url('/cctv#turno-activo'));
        }

        $equipmentItems = $this->equipment->listActive();

        $this->cameraView('shifts/reception', [
            'title' => 'Recepción del puesto',
            'equipmentItems' => $equipmentItems,
            'statuses' => EquipmentCheckCatalog::statuses(),
            'moduleScripts' => $this->cctvScripts('shift.js'),
        ]);
    }

    public function store(Request $request): void
    {
        if (!hasPermission('cctv.shifts.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede iniciar turnos.');
            $this->redirect(url('/cctv'));
        }

        $payload = $request->all();
        $equipmentItems = $this->equipment->listActive();
        $errors = (new ShiftReceptionValidator($equipmentItems))->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Complete la recepción de equipos antes de iniciar el turno.');
            $this->redirect(url('/cctv/shifts/create'));
        }

        try {
            $this->shifts->openWithReception($payload);
        } catch (OpenShiftAlreadyExistsException $e) {
            Session::flashAlert('warning', 'Turno CCTV', 'Ya posee un turno CCTV abierto.');
            $this->redirect(url('/cctv#turno-activo'));
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/cctv/shifts/create'));
        }

        Session::flashAlert('success', 'Turno iniciado', 'La recepción del puesto quedó registrada y su turno operativo está abierto.');
        $this->redirect(url('/cctv#turno-activo'));
    }

    public function closeForm(): void
    {
        if (!hasPermission('cctv.shifts.close')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede finalizar turnos.');
            $this->redirect(url('/cctv'));
        }

        $operatorId = Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            Session::flashAlert('warning', 'Sesión inválida', 'Debe iniciar sesión para finalizar el turno.');
            $this->redirect(url('/cctv'));
        }

        $openShift = $this->shifts->findOpenForOperator($operatorId);
        if ($openShift === null) {
            Session::flashAlert('warning', 'Sin turno activo', 'No tiene un turno abierto para finalizar.');
            $this->redirect(url('/cctv'));
        }

        $this->cameraView('shifts/close', [
            'title' => 'Finalizar Turno',
            'openShift' => $openShift,
            'closingSummary' => $this->shifts->closingSummary($openShift),
            'equipmentItems' => $this->equipment->listActive(),
            'statuses' => EquipmentCheckCatalog::statuses(),
            'moduleScripts' => $this->cctvScripts('shift.js', 'unsaved.js'),
        ]);
    }

    public function close(Request $request): void
    {
        if (!hasPermission('cctv.shifts.close')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede finalizar turnos.');
            $this->redirect(url('/cctv'));
        }

        $operatorId = Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            Session::flashAlert('warning', 'Sesión inválida', 'Debe iniciar sesión para finalizar el turno.');
            $this->redirect(url('/cctv'));
        }

        $openShift = $this->shifts->findOpenForOperator($operatorId);
        if ($openShift === null) {
            Session::flashAlert('warning', 'Sin turno activo', 'No tiene un turno abierto para finalizar.');
            $this->redirect(url('/cctv'));
        }

        $payload = $request->all();
        $equipmentItems = $this->equipment->listActive();
        $errors = (new ShiftReceptionValidator(
            $equipmentItems,
            'closing_notes',
            'No hay equipos configurados para la entrega del puesto.'
        ))->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Complete la entrega de equipos antes de finalizar el turno.');
            $this->redirect(url('/cctv/shifts/close'));
        }

        try {
            $this->shifts->closeWithDelivery((int) ($openShift['id'] ?? 0), $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/cctv/shifts/close'));
        }

        Session::flashAlert('success', 'Turno finalizado', 'La entrega del puesto quedó registrada y el turno operativo se cerró correctamente.');
        $this->redirect(url('/cctv'));
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

    /**
     * @return array<string, string>
     */
    private function listFilters(Request $request): array
    {
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $status = trim((string) $request->query('status', ''));

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) {
            $dateFrom = '';
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1) {
            $dateTo = '';
        }

        if ($status !== '' && !\App\Models\Cctv\Shift::isValidStatus($status)) {
            $status = '';
        }

        $operatorId = '';
        if (hasPermission('cctv.shifts.view_all')) {
            $operatorId = trim((string) $request->query('operator_id', ''));
            if ($operatorId !== '' && (int) $operatorId < 1) {
                $operatorId = '';
            }
        } elseif (Auth::id() !== null) {
            $operatorId = (string) Auth::id();
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => $status,
            'operator_id' => $operatorId,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusFilterOptions(): array
    {
        return [
            ['value' => \App\Models\Cctv\Shift::STATUS_OPEN, 'label' => 'Abierto'],
            ['value' => \App\Models\Cctv\Shift::STATUS_CLOSED, 'label' => 'Cerrado'],
        ];
    }
}
