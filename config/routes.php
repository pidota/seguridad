<?php

declare(strict_types=1);

use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ModulePlaceholderController;
use App\Controllers\PasswordController;
use App\Controllers\PermissionController;
use App\Controllers\ProfileController;
use App\Controllers\RoleController;
use App\Controllers\SectorController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use Core\Router;

/** @var Router $router */

$router->get('/', [DashboardController::class, 'home'], [], 'home');

$router->get('/login', [AuthController::class, 'showLogin'], 'guest', 'login');
$router->post('/login', [AuthController::class, 'login'], 'guest', 'login.attempt');
$router->post('/logout', [AuthController::class, 'logout'], 'auth', 'logout');

$router->get('/password/change', [PasswordController::class, 'showChange'], 'auth', 'password.change');
$router->post('/password/change', [PasswordController::class, 'update'], 'auth', 'password.update');

$router->get('/password/forgot', [PasswordController::class, 'showForgot'], 'guest', 'password.forgot');
$router->post('/password/forgot', [PasswordController::class, 'forgot'], 'guest', 'password.forgot.attempt');

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth', 'can:dashboard.access'], 'dashboard');

$router->get('/profile', [ProfileController::class, 'show'], 'auth', 'profile');
$router->get('/settings', [SettingsController::class, 'index'], ['auth', 'can:settings.access'], 'settings');

$router->get('/users', [UserController::class, 'index'], ['auth', 'can:users.view'], 'users.index');
$router->get('/users/create', [UserController::class, 'create'], ['auth', 'can:users.create'], 'users.create');
$router->post('/users', [UserController::class, 'store'], ['auth', 'can:users.create'], 'users.store');
$router->get('/users/{id}/edit', [UserController::class, 'edit'], ['auth', 'can:users.update'], 'users.edit');
$router->put('/users/{id}', [UserController::class, 'update'], ['auth', 'can:users.update'], 'users.update');
$router->delete('/users/{id}', [UserController::class, 'destroy'], ['auth', 'can:users.delete'], 'users.destroy');

$router->get('/roles', [RoleController::class, 'index'], ['auth', 'can:roles.view'], 'roles.index');
$router->get('/roles/create', [RoleController::class, 'create'], ['auth', 'can:roles.create'], 'roles.create');
$router->post('/roles', [RoleController::class, 'store'], ['auth', 'can:roles.create'], 'roles.store');
$router->get('/roles/{id}/edit', [RoleController::class, 'edit'], ['auth', 'can:roles.update'], 'roles.edit');
$router->put('/roles/{id}', [RoleController::class, 'update'], ['auth', 'can:roles.update'], 'roles.update');
$router->delete('/roles/{id}', [RoleController::class, 'destroy'], ['auth', 'can:roles.delete'], 'roles.destroy');

$router->get('/permissions', [PermissionController::class, 'index'], ['auth', 'can:permissions.view'], 'permissions.index');

$router->get('/sectors', [SectorController::class, 'index'], ['auth', 'can:sectors.view'], 'sectors.index');
$router->get('/sectors/create', [SectorController::class, 'create'], ['auth', 'can:sectors.create'], 'sectors.create');
$router->post('/sectors', [SectorController::class, 'store'], ['auth', 'can:sectors.create'], 'sectors.store');
$router->get('/sectors/{id}/edit', [SectorController::class, 'edit'], ['auth', 'can:sectors.update'], 'sectors.edit');
$router->put('/sectors/{id}', [SectorController::class, 'update'], ['auth', 'can:sectors.update'], 'sectors.update');
$router->delete('/sectors/{id}', [SectorController::class, 'destroy'], ['auth', 'can:sectors.delete'], 'sectors.destroy');

$router->get('/audit', [AuditController::class, 'index'], ['auth', 'can:audit.view'], 'audit.index');
$router->get('/audit/{id}', [AuditController::class, 'show'], ['auth', 'can:audit.view'], 'audit.show');

$router->group('/senda', ['auth', 'can:senda.access'], static function (Router $router): void {
    require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'senda.php';
});

$router->group('/cctv', ['auth', 'can:cctv.access'], static function (Router $router): void {
    require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'cctv.php';
});

$router->group('/women', ['auth', 'can:women.access'], static function (Router $router): void {
    require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'women.php';
});

$router->group('/meetings', ['auth', 'can:meetings.access'], static function (Router $router): void {
    require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'meetings.php';
});

$router->get('/guards', [ModulePlaceholderController::class, 'guards'], ['auth', 'can:guards.access'], 'guards.index');
