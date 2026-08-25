<?php

declare(strict_types=1);

use App\Controllers\Guards\DashboardController;
use App\Controllers\Guards\LogEntryController;
use App\Controllers\Guards\ShiftController;
use Core\Router;

/** @var Router $router */

$router->get('/', [DashboardController::class, 'index'], [], 'guards.dashboard');

$router->get('/shifts', [ShiftController::class, 'index'], 'can:guards.shifts.view', 'guards.shifts.index');
$router->get('/shifts/create', [ShiftController::class, 'create'], 'can:guards.shifts.create', 'guards.shifts.create');
$router->post('/shifts', [ShiftController::class, 'store'], 'can:guards.shifts.create', 'guards.shifts.store');
$router->get('/shifts/close', [ShiftController::class, 'closeForm'], 'can:guards.shifts.close', 'guards.shifts.close.form');
$router->post('/shifts/close', [ShiftController::class, 'close'], 'can:guards.shifts.close', 'guards.shifts.close');
$router->get('/shifts/{id}', [ShiftController::class, 'show'], 'can:guards.shifts.view', 'guards.shifts.show');

$router->get('/log', [LogEntryController::class, 'index'], 'can:guards.log.view', 'guards.log.index');
$router->get('/log/create', [LogEntryController::class, 'create'], 'can:guards.log.create', 'guards.log.create');
$router->post('/log', [LogEntryController::class, 'store'], 'can:guards.log.create', 'guards.log.store');
$router->get('/log/{id}', [LogEntryController::class, 'show'], 'can:guards.log.view', 'guards.log.show');
$router->get('/cctv-coordinations', [LogEntryController::class, 'cctvCoordinations'], 'can:guards.log.view', 'guards.cctv.coordinations');
