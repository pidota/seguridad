<?php

declare(strict_types=1);

namespace App\Controllers\Cctv;

use App\Controllers\Camera\CameraController as CctvLayoutController;
use App\Services\Cctv\HandoverCatalog;
use App\Services\Cctv\LogHandoverService;
use App\Services\Cctv\ShiftService;
use Core\Auth;
use Core\Exceptions\HttpException;
use Core\Request;
use Core\Session;

final class HandoverController extends CctvLayoutController
{
    public function __construct(
        private readonly LogHandoverService $handovers = new LogHandoverService(),
        private readonly ShiftService $shifts = new ShiftService()
    ) {
    }

    public function index(): void
    {
        if (!hasPermission('cctv.handovers.view')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede consultar traspasos.');
            $this->redirect(url('/cctv'));
        }

        $operatorId = Auth::id();
        $openShift = $operatorId ? $this->shifts->findOpenForOperator($operatorId) : null;

        if ($openShift === null) {
            Session::flashAlert('warning', 'Sin turno activo', 'Debe iniciar un turno para revisar pendientes recibidos.');
            $this->redirect(url('/cctv'));
        }

        $shiftId = (int) ($openShift['id'] ?? 0);
        $pending = $this->handovers->listPendingForShift($shiftId, $operatorId);

        $this->cameraView('handovers/index', [
            'title' => 'Pendientes recibidos',
            'openShift' => $openShift,
            'pendingHandovers' => $pending,
            'pendingCount' => count($pending),
            'canAccept' => hasPermission('cctv.handovers.accept'),
            'canDecline' => hasPermission('cctv.handovers.decline'),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        if (!hasPermission('cctv.handovers.view')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede consultar traspasos.');
            $this->redirect(url('/cctv'));
        }

        $handoverId = (int) $id;
        if ($handoverId < 1) {
            Session::flashAlert('error', 'Traspaso inválido', 'El identificador no es válido.');
            $this->redirect(url('/cctv/handovers'));
        }

        try {
            $detail = $this->handovers->reviewDetail($handoverId, Auth::id());
        } catch (HttpException $e) {
            Session::flashAlert(
                $e->getStatusCode() === 403 ? 'warning' : 'error',
                'No se pudo consultar el traspaso',
                $e->getMessage()
            );
            $this->redirect(url('/cctv/handovers'));
        }

        $this->cameraView('handovers/show', [
            'title' => 'Revisión de pendiente',
            'handover' => $detail,
            'canAccept' => hasPermission('cctv.handovers.accept'),
            'canDecline' => hasPermission('cctv.handovers.decline'),
            'declineReasons' => HandoverCatalog::declineReasonOptions(),
            'institutions' => HandoverCatalog::delegatedInstitutions(),
            'moduleScripts' => $this->cctvScripts('handover.js'),
        ]);
    }

    public function accept(Request $request, string $id): void
    {
        if (!hasPermission('cctv.handovers.accept')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede aceptar continuidad.');
            $this->redirect(url('/cctv/handovers'));
        }

        $handoverId = (int) $id;
        try {
            $this->handovers->accept($handoverId, Auth::id());
        } catch (HttpException $e) {
            Session::flashAlert(
                $e->getStatusCode() === 403 ? 'warning' : 'error',
                'No se pudo aceptar continuidad',
                $e->getMessage()
            );
            $this->redirect(url('/cctv/handovers/' . $handoverId));
        }

        Session::flashAlert('success', 'Continuidad aceptada', 'El procedimiento quedó bajo su gestión en el turno actual.');
        $detail = $this->handovers->reviewDetail($handoverId, Auth::id());
        $entryId = (int) ($detail['log_entry_id'] ?? 0);
        $this->redirect(url('/cctv/log/' . $entryId));
    }

    public function decline(Request $request, string $id): void
    {
        if (!hasPermission('cctv.handovers.decline')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede declinar continuidad.');
            $this->redirect(url('/cctv/handovers'));
        }

        $handoverId = (int) $id;

        try {
            $this->handovers->decline($handoverId, $request->all(), Auth::id());
        } catch (HttpException $e) {
            Session::flashInput($request->all());
            Session::flashAlert(
                $e->getStatusCode() === 403 ? 'warning' : 'error',
                'No se pudo registrar la decisión',
                $e->getMessage()
            );
            $this->redirect(url('/cctv/handovers/' . $handoverId));
        }

        Session::flashAlert('success', 'Decisión registrada', 'El procedimiento fue finalizado con la justificación indicada.');
        $this->redirect(url('/cctv/handovers'));
    }
}
