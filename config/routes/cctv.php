<?php

declare(strict_types=1);

use App\Controllers\Cctv\CameraController;
use App\Controllers\Cctv\LogEntryController;
use App\Controllers\Cctv\OfficeVisitController;
use App\Controllers\Cctv\ShiftController;
use App\Controllers\Camera\DashboardController;
use App\Controllers\Camera\EventController;
use Core\Router;

/** @var Router $router */

$router->get('/', [DashboardController::class, 'index'], 'can:cctv.dashboard.view', 'cctv.dashboard');

$router->get('/shifts', [ShiftController::class, 'index'], 'can:cctv.shifts.view', 'cctv.shifts.index');

$router->get('/shifts/create', [ShiftController::class, 'create'], 'can:cctv.shifts.create', 'cctv.shifts.create');

$router->post('/shifts', [ShiftController::class, 'store'], 'can:cctv.shifts.create', 'cctv.shifts.store');

$router->get('/shifts/close', [ShiftController::class, 'closeForm'], 'can:cctv.shifts.close', 'cctv.shifts.close.form');
$router->post('/shifts/close', [ShiftController::class, 'close'], 'can:cctv.shifts.close', 'cctv.shifts.close');

$router->get('/shifts/{id}', [ShiftController::class, 'show'], 'can:cctv.shifts.view', 'cctv.shifts.show');

$router->get('/log', [EventController::class, 'index'], 'can:cctv.log.view', 'cctv.log.index');
$router->get('/log/create', [LogEntryController::class, 'create'], 'can:cctv.log.create', 'cctv.log.create');
$router->post('/log', [LogEntryController::class, 'store'], 'can:cctv.log.create', 'cctv.log.store');
$router->get('/log/incident/create', [LogEntryController::class, 'createIncident'], 'can:cctv.log.create', 'cctv.log.incident.create');
$router->post('/log/incident', [LogEntryController::class, 'storeIncident'], 'can:cctv.log.create', 'cctv.log.incident.store');
$router->get('/log/technical/create', [LogEntryController::class, 'createTechnical'], 'can:cctv.log.create', 'cctv.log.technical.create');
$router->post('/log/technical', [LogEntryController::class, 'storeTechnical'], 'can:cctv.log.create', 'cctv.log.technical.store');
$router->get('/log/{id}', [LogEntryController::class, 'show'], 'can:cctv.log.view', 'cctv.log.show');
$router->get('/log/{id}/edit', [EventController::class, 'edit'], 'can:cctv.log.edit', 'cctv.log.edit');
$router->put('/log/{id}', [EventController::class, 'update'], 'can:cctv.log.edit', 'cctv.log.update');
$router->delete('/log/{id}', [LogEntryController::class, 'destroy'], 'can:cctv.log.delete', 'cctv.log.destroy');

$router->get('/visits', [OfficeVisitController::class, 'index'], 'can:cctv.visits.view', 'cctv.visits.index');
$router->get('/visits/create', [OfficeVisitController::class, 'create'], 'can:cctv.visits.create', 'cctv.visits.create');
$router->post('/visits', [OfficeVisitController::class, 'store'], 'can:cctv.visits.create', 'cctv.visits.store');
$router->get('/visits/search-rut', [OfficeVisitController::class, 'searchByRut'], 'can:cctv.recordings.view', 'cctv.visits.search_rut');
$router->get('/visits/{id}', [OfficeVisitController::class, 'show'], 'can:cctv.visits.view', 'cctv.visits.show');
$router->post('/visits/{id}/departure', [OfficeVisitController::class, 'registerDeparture'], 'can:cctv.visits.edit', 'cctv.visits.departure');

$router->get('/recording-requests/{id}', [OfficeVisitController::class, 'showRecording'], 'can:cctv.recordings.view', 'cctv.recordings.show');
$router->get('/recording-requests/{id}/print', [OfficeVisitController::class, 'printRecording'], 'can:cctv.recordings.view', 'cctv.recordings.print');
$router->get('/recording-requests/{id}/receipt', [OfficeVisitController::class, 'receiptRecording'], 'can:cctv.recordings.view', 'cctv.recordings.receipt');
$router->post('/recording-requests/{id}/complaint', [OfficeVisitController::class, 'registerComplaint'], 'can:cctv.recordings.edit', 'cctv.recordings.complaint');
$router->post('/recording-requests/{id}/verify-complaint', [OfficeVisitController::class, 'verifyComplaint'], 'can:cctv.recordings.verify_complaint', 'cctv.recordings.verify_complaint');
$router->post('/recording-requests/{id}/status', [OfficeVisitController::class, 'updateStatus'], 'can:cctv.recordings.review', 'cctv.recordings.status');
$router->post('/recording-requests/{id}/preserve', [OfficeVisitController::class, 'preserveRecording'], 'can:cctv.recordings.review', 'cctv.recordings.preserve');
$router->post('/recording-requests/{id}/assign', [OfficeVisitController::class, 'assignRecording'], 'can:cctv.recordings.assign', 'cctv.recordings.assign');
$router->post('/recording-requests/{id}/cancel', [OfficeVisitController::class, 'cancelRecording'], 'can:cctv.recordings.cancel', 'cctv.recordings.cancel');
$router->post('/recording-requests/{id}/deliver', [OfficeVisitController::class, 'deliver'], 'can:cctv.recordings.deliver', 'cctv.recordings.deliver');
$router->get('/recording-requests/{id}/complaint-document', [OfficeVisitController::class, 'complaintDocument'], 'can:cctv.recordings.view_complaint_document', 'cctv.recordings.complaint_document');

$router->get('/cameras', [CameraController::class, 'index'], 'can:cctv.cameras.view', 'cctv.cameras.index');
$router->get('/cameras/create', [CameraController::class, 'create'], 'can:cctv.cameras.manage', 'cctv.cameras.create');
$router->post('/cameras', [CameraController::class, 'store'], 'can:cctv.cameras.manage', 'cctv.cameras.store');
$router->get('/cameras/{id}/edit', [CameraController::class, 'edit'], 'can:cctv.cameras.manage', 'cctv.cameras.edit');
$router->put('/cameras/{id}', [CameraController::class, 'update'], 'can:cctv.cameras.manage', 'cctv.cameras.update');
$router->delete('/cameras/{id}', [CameraController::class, 'destroy'], 'can:cctv.cameras.manage', 'cctv.cameras.destroy');
