<?php

declare(strict_types=1);

namespace App\Controllers\Cctv;

use App\Controllers\Camera\CameraController as CctvLayoutController;
use App\Services\Cctv\CameraCatalog;
use App\Services\Cctv\CameraService;
use App\Validators\Cctv\CameraStoreValidator;
use App\Validators\Cctv\CameraUpdateValidator;
use Core\Exceptions\HttpException;
use Core\Request;
use Core\Session;

final class CameraController extends CctvLayoutController
{
    public function __construct(
        private readonly CameraService $cameras = new CameraService()
    ) {
    }

    public function index(Request $request): void
    {
        $canManage = hasPermission('cctv.cameras.manage');
        $filters = $this->listFilters($request, $canManage);
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->cameras->search($filters, $page, 15, !$canManage);

        $this->cameraView('cameras/index', [
            'title' => 'Inventario de cámaras',
            'cameras' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'canManage' => $canManage,
            'cameraTypes' => CameraCatalog::types(),
            'statuses' => CameraCatalog::statuses(),
            'sectors' => $this->cameras->sectorOptions(),
        ]);
    }

    public function create(): void
    {
        $this->cameraView('cameras/form', array_merge([
            'title' => 'Registrar cámara',
            'record' => $this->cameras->defaults(),
            'cameraTypes' => CameraCatalog::types(),
            'statuses' => CameraCatalog::statuses(),
            'sectors' => $this->cameras->sectorOptions(),
            'moduleScripts' => $this->cctvScripts('cameras.js'),
        ], $this->mapViewData()));
    }

    public function store(Request $request): void
    {
        $payload = $request->all();
        $errors = (new CameraStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/cctv/cameras/create'));
        }

        try {
            $this->cameras->create($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/cctv/cameras/create'));
        }

        Session::flashAlert('success', 'Cámara registrada', 'El equipo quedó en el inventario CCTV.');
        $this->redirect(url('/cctv/cameras'));
    }

    public function edit(Request $request, string $id): void
    {
        try {
            $record = $this->cameras->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/cctv/cameras'));
        }

        $this->cameraView('cameras/form', array_merge([
            'title' => 'Editar cámara',
            'record' => $record,
            'cameraTypes' => CameraCatalog::types(),
            'statuses' => CameraCatalog::statuses(),
            'sectors' => $this->cameras->sectorOptions(),
            'moduleScripts' => $this->cctvScripts('cameras.js'),
        ], $this->mapViewData()));
    }

    public function update(Request $request, string $id): void
    {
        try {
            $this->cameras->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/cctv/cameras'));
        }

        $payload = $request->all();
        $editUrl = url('/cctv/cameras/' . $id . '/edit');
        $errors = (new CameraUpdateValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect($editUrl);
        }

        try {
            $this->cameras->update((int) $id, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, $editUrl);
        }

        Session::flashAlert('success', 'Cámara actualizada', 'Los cambios quedaron registrados en auditoría.');
        $this->redirect(url('/cctv/cameras'));
    }

    public function destroy(Request $request, string $id): void
    {
        try {
            $this->cameras->delete((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/cctv/cameras'));
        }

        Session::flashAlert('success', 'Cámara eliminada', 'El registro fue dado de baja del inventario.');
        $this->redirect(url('/cctv/cameras'));
    }

    public function map(): void
    {
        $canManage = hasPermission('cctv.cameras.manage');
        $cameras = $this->cameras->listForMap(!$canManage);

        $this->cameraView('cameras/map', array_merge([
            'title' => 'Mapa de cámaras',
            'cameras' => $cameras,
            'canManage' => $canManage,
            'moduleScripts' => $this->cctvScripts('cameras-map.js'),
        ], $this->mapViewData()));
    }

    /**
     * @return array<string, mixed>
     */
    private function listFilters(Request $request, bool $canManage): array
    {
        $cameraType = trim((string) $request->query('camera_type', ''));
        $status = trim((string) $request->query('status', ''));

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'sector_id' => trim((string) $request->query('sector_id', '')),
            'camera_type' => CameraCatalog::isValidType($cameraType) ? $cameraType : '',
            'status' => CameraCatalog::isValidStatus($status) ? $status : '',
        ];

        if ($canManage) {
            $filters['active'] = trim((string) $request->query('active', ''));
        }

        return $filters;
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
