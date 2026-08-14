<?php

declare(strict_types=1);

use App\Controllers\Cctv\CameraController;
use App\Controllers\Cctv\LogEntryController;
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

$router->get('/cameras', [CameraController::class, 'index'], 'can:cctv.cameras.view', 'cctv.cameras.index');
$router->get('/cameras/create', [CameraController::class, 'create'], 'can:cctv.cameras.manage', 'cctv.cameras.create');
$router->post('/cameras', [CameraController::class, 'store'], 'can:cctv.cameras.manage', 'cctv.cameras.store');
$router->get('/cameras/{id}/edit', [CameraController::class, 'edit'], 'can:cctv.cameras.manage', 'cctv.cameras.edit');
$router->put('/cameras/{id}', [CameraController::class, 'update'], 'can:cctv.cameras.manage', 'cctv.cameras.update');
$router->delete('/cameras/{id}', [CameraController::class, 'destroy'], 'can:cctv.cameras.manage', 'cctv.cameras.destroy');
