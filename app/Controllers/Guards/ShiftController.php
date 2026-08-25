<?php

declare(strict_types=1);

namespace App\Controllers\Guards;

use App\Exceptions\Guards\OpenShiftAlreadyExistsException;
use App\Models\Guards\Shift;
use App\Services\Guards\ShiftService;
use App\Validators\Guards\ShiftStoreValidator;
use Core\Auth;
use Core\Exceptions\HttpException;
use Core\Request;
use Core\Session;

final class ShiftController extends GuardsController
{
    public function __construct(private readonly ShiftService $shifts = new ShiftService())
    {
    }

    public function index(Request $request): void
    {
        $filters = $this->listFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->shifts->searchHistory($filters, $page);

        $this->guardsView('shifts/index', [
            'title' => 'Historial de turnos',
            'shifts' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'statuses' => $this->statusFilterOptions(),
            'guards' => hasPermission('guards.shifts.view_all') ? $this->shifts->guardOptions() : [],
            'canViewAll' => hasPermission('guards.shifts.view_all'),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $shiftId = (int) $id;
        if ($shiftId < 1) {
            Session::flashAlert('error', 'Turno no encontrado', 'El identificador no es válido.');
            $this->redirect(url('/guards/shifts'));
        }

        try {
            $detail = $this->shifts->detailForView($shiftId, Auth::id());
        } catch (HttpException $e) {
            Session::flashAlert(
                $e->getStatusCode() === 403 ? 'warning' : 'error',
                'No se pudo consultar el turno',
                $e->getMessage()
            );
            $this->redirect(url('/guards/shifts'));
        }

        $this->guardsView('shifts/show', [
            'title' => 'Detalle de turno',
            'shift' => $detail['shift'],
            'stats' => $detail['stats'],
            'logEntries' => $detail['log_entries'],
            'canCreateLog' => hasPermission('guards.log.create') && !empty($detail['shift']['is_open']),
            'canViewCctv' => hasPermission('cctv.log.view'),
        ]);
    }

    public function create(): void
    {
        if (!hasPermission('guards.shifts.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede iniciar turnos.');
            $this->redirect(url('/guards'));
        }

        $guardId = Auth::id();
        if ($guardId === null || $guardId < 1) {
            Session::flashAlert('warning', 'Sesión inválida', 'Debe iniciar sesión.');
            $this->redirect(url('/guards'));
        }

        if ($this->shifts->findOpenForGuard($guardId) !== null) {
            Session::flashAlert('warning', 'Turno activo', 'Ya posee un turno abierto.');
            $this->redirect(url('/guards#turno-activo'));
        }

        $this->guardsView('shifts/create', [
            'title' => 'Iniciar turno',
            'defaults' => [
                'shift_date' => date('Y-m-d'),
                'opening_notes' => '',
            ],
        ]);
    }

    public function store(Request $request): void
    {
        if (!hasPermission('guards.shifts.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede iniciar turnos.');
            $this->redirect(url('/guards'));
        }

        $payload = $request->all();
        if (!isset($payload['shift_date']) || trim((string) $payload['shift_date']) === '') {
            $payload['shift_date'] = date('Y-m-d');
        }

        $errors = (new ShiftStoreValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Complete los datos del turno.');
            $this->redirect(url('/guards/shifts/create'));
        }

        try {
            $this->shifts->open($payload);
        } catch (OpenShiftAlreadyExistsException) {
            Session::flashAlert('warning', 'Turno activo', 'Ya posee un turno abierto.');
            $this->redirect(url('/guards#turno-activo'));
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/guards/shifts/create'));
        }

        Session::flashAlert('success', 'Turno iniciado', 'Su turno operativo en terreno quedó abierto.');
        $this->redirect(url('/guards#turno-activo'));
    }

    public function closeForm(): void
    {
        if (!hasPermission('guards.shifts.close')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede finalizar turnos.');
            $this->redirect(url('/guards'));
        }

        $guardId = Auth::id();
        if ($guardId === null || $guardId < 1) {
            Session::flashAlert('warning', 'Sesión inválida', 'Debe iniciar sesión.');
            $this->redirect(url('/guards'));
        }

        $openShift = $this->shifts->findOpenForGuard($guardId);
        if ($openShift === null) {
            Session::flashAlert('warning', 'Sin turno activo', 'No tiene un turno abierto.');
            $this->redirect(url('/guards'));
        }

        $this->guardsView('shifts/close', [
            'title' => 'Finalizar turno',
            'openShift' => $openShift,
        ]);
    }

    public function close(Request $request): void
    {
        if (!hasPermission('guards.shifts.close')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede finalizar turnos.');
            $this->redirect(url('/guards'));
        }

        $guardId = Auth::id();
        if ($guardId === null || $guardId < 1) {
            Session::flashAlert('warning', 'Sesión inválida', 'Debe iniciar sesión.');
            $this->redirect(url('/guards'));
        }

        $openShift = $this->shifts->findOpenForGuard($guardId);
        if ($openShift === null) {
            Session::flashAlert('warning', 'Sin turno activo', 'No tiene un turno abierto.');
            $this->redirect(url('/guards'));
        }

        try {
            $this->shifts->close((int) ($openShift['id'] ?? 0), (string) $request->input('closing_notes', ''));
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/guards/shifts/close'));
        }

        Session::flashAlert('success', 'Turno finalizado', 'El turno operativo se cerró correctamente.');
        $this->redirect(url('/guards'));
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

        if ($status !== '' && !Shift::isValidStatus($status)) {
            $status = '';
        }

        $guardId = '';
        if (hasPermission('guards.shifts.view_all')) {
            $guardId = trim((string) $request->query('guard_id', ''));
            if ($guardId !== '' && (int) $guardId < 1) {
                $guardId = '';
            }
        } elseif (Auth::id() !== null) {
            $guardId = (string) Auth::id();
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => $status,
            'guard_id' => $guardId,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusFilterOptions(): array
    {
        return [
            ['value' => Shift::STATUS_OPEN, 'label' => 'Abierto'],
            ['value' => Shift::STATUS_CLOSED, 'label' => 'Cerrado'],
        ];
    }
}
