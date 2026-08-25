<?php

declare(strict_types=1);

namespace App\Controllers\Guards;

use App\Repositories\SectorRepository;
use App\Services\Guards\CctvLinkService;
use App\Services\Guards\LogEntryCatalog;
use App\Services\Guards\LogEntryService;
use App\Services\Guards\ShiftService;
use App\Validators\Guards\LogEntryStoreValidator;
use Core\Auth;
use Core\Exceptions\HttpException;
use Core\Request;
use Core\Session;

final class LogEntryController extends GuardsController
{
    public function __construct(
        private readonly LogEntryService $logEntries = new LogEntryService(),
        private readonly ShiftService $shifts = new ShiftService(),
        private readonly CctvLinkService $cctvLink = new CctvLinkService(),
        private readonly SectorRepository $sectors = new SectorRepository()
    ) {
    }

    public function index(Request $request): void
    {
        $filters = $this->listFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->logEntries->searchHistory($filters, $page);

        $this->guardsView('log/index', [
            'title' => 'Bitácora de terreno',
            'entries' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'logTypes' => $this->logEntries->logTypeOptions(),
            'guards' => hasPermission('guards.shifts.view_all') ? $this->shifts->guardOptions() : [],
            'canViewAll' => hasPermission('guards.shifts.view_all'),
            'canCreate' => hasPermission('guards.log.create') && $this->shifts->findOpenForGuard((int) Auth::id()) !== null,
            'canViewCctv' => hasPermission('cctv.log.view'),
        ]);
    }

    public function create(): void
    {
        if (!hasPermission('guards.log.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede registrar novedades.');
            $this->redirect(url('/guards'));
        }

        $guardId = Auth::id();
        if ($guardId === null || $this->shifts->findOpenForGuard($guardId) === null) {
            Session::flashAlert('warning', 'Sin turno activo', 'Debe iniciar un turno antes de registrar novedades.');
            $this->redirect(url('/guards'));
        }

        $this->guardsView('log/create', [
            'title' => 'Registrar novedad',
            'defaults' => $this->logEntries->defaults(),
            'logTypes' => $this->logEntries->logTypeOptions(),
            'sectors' => $this->sectors->options(),
            'statuses' => LogEntryCatalog::statusOptions(),
            'canLinkCctv' => hasPermission('guards.log.link_cctv'),
        ]);
    }

    public function store(Request $request): void
    {
        if (!hasPermission('guards.log.create')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede registrar novedades.');
            $this->redirect(url('/guards'));
        }

        $payload = $request->all();
        $errors = (new LogEntryStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Complete los datos de la novedad.');
            $this->redirect(url('/guards/log/create'));
        }

        try {
            $result = $this->logEntries->createForOpenShift($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/guards/log/create'));
        }

        if (!empty($result['cctv_linked'])) {
            Session::flashAlert('success', 'Novedad registrada', 'La novedad quedó registrada y se notificó al monitoreo CCTV.');
        } elseif (!empty($result['cctv_pending'])) {
            Session::flashAlert(
                'warning',
                'Novedad registrada',
                'La novedad quedó registrada, pero no hay turno CCTV abierto para recibir la notificación.'
            );
        } else {
            Session::flashAlert('success', 'Novedad registrada', 'La novedad quedó registrada en la bitácora de terreno.');
        }

        $this->redirect(url('/guards#turno-activo'));
    }

    public function show(Request $request, string $id): void
    {
        $entryId = (int) $id;
        if ($entryId < 1) {
            Session::flashAlert('error', 'Novedad no encontrada', 'El identificador no es válido.');
            $this->redirect(url('/guards/log'));
        }

        try {
            $entry = $this->logEntries->find($entryId);
        } catch (HttpException $e) {
            Session::flashAlert(
                $e->getStatusCode() === 403 ? 'warning' : 'error',
                'No se pudo consultar la novedad',
                $e->getMessage()
            );
            $this->redirect(url('/guards/log'));
        }

        $this->guardsView('log/show', [
            'title' => 'Detalle de novedad',
            'entry' => $entry,
            'canViewCctv' => hasPermission('cctv.log.view'),
        ]);
    }

    public function cctvCoordinations(): void
    {
        $this->guardsView('log/cctv-coordinations', [
            'title' => 'Coordinaciones desde monitoreo CCTV',
            'coordinations' => $this->cctvLink->incomingCoordinations(50),
            'total' => $this->cctvLink->incomingCount(),
            'canViewCctv' => hasPermission('cctv.log.view'),
        ]);
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
        $logTypeId = trim((string) $request->query('log_type_id', ''));

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) {
            $dateFrom = '';
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1) {
            $dateTo = '';
        }

        if ($logTypeId !== '' && (int) $logTypeId < 1) {
            $logTypeId = '';
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
            'log_type_id' => $logTypeId,
            'guard_id' => $guardId,
        ];
    }
}
