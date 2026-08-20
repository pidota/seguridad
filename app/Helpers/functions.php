<?php

declare(strict_types=1);

use Core\App;
use Core\Auth;
use Core\Csrf;
use Core\Env;
use Core\Permission;
use Core\Request;
use Core\Session;
use Core\View;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function cctv_config(string $key, mixed $default = null): mixed
{
    static $cctv = null;

    if ($cctv === null) {
        $cctv = require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cctv.php';
    }

    return $cctv[$key] ?? $default;
}

function config(string $key, mixed $default = null): mixed
{
    static $items = [];

    if ($items === []) {
        $items['app'] = require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        $items['database'] = require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
        $items['cctv'] = require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cctv.php';
        $items['mail'] = require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'mail.php';
    }

    $segments = explode('.', $key);
    $value = $items;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function url(string $path = ''): string
{
    $base = rtrim(Request::baseUrl(), '/');
    $path = '/' . ltrim($path, '/');

    if ($path === '/') {
        return $base . '/';
    }

    return $base . $path;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function route(string $name, array $params = []): string
{
    return App::getInstance()->router()->url($name, $params);
}

function redirect(string $to): never
{
    Core\Response::redirect($to);
}

function old(string $key, mixed $default = ''): mixed
{
    return Session::old($key, $default);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return Csrf::field();
}

function method_field(string $method): string
{
    $method = strtoupper($method);

    return '<input type="hidden" name="_method" value="' . e($method) . '">';
}

function auth(): Auth
{
    return new Auth();
}

function user(): ?array
{
    return Auth::user();
}

function error(string $field): ?string
{
    return Session::error($field);
}

function errors(): array
{
    return Session::errors();
}

function has_error(string $field): bool
{
    return Session::error($field) !== null;
}

function component(string $name, array $data = []): string
{
    return View::component($name, $data);
}

function flash_alert(): ?array
{
    $alert = Session::getFlash('alert');

    return is_array($alert) ? $alert : null;
}

function is_active_path(string $path): bool
{
    $current = Request::capture()->path();
    $path = '/' . trim($path, '/');

    if ($path === '/') {
        return $current === '/';
    }

    return $current === $path || str_starts_with($current, $path . '/');
}

function is_current_path(string $path): bool
{
    $current = rtrim(Request::capture()->path(), '/') ?: '/';
    $path = '/' . trim($path, '/');
    $path = $path === '/' ? '/' : (rtrim($path, '/') ?: '/');

    return $current === $path;
}

function is_active_group(array $paths): bool
{
    foreach ($paths as $path) {
        if (is_active_path((string) $path)) {
            return true;
        }
    }

    return false;
}

function hasAnyPermission(string ...$permissions): bool
{
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }

    return false;
}

function hasPermission(string $permission): bool
{
    return Permission::has($permission);
}

function requirePermission(string $permission): void
{
    Permission::require($permission);
}

function can(string $permission): bool
{
    return Permission::has($permission);
}

function senda_nav(array $nav): string
{
    return View::make('senda/components/nav', [
        'sendaNav' => $nav,
    ], null);
}

function cctv_nav(array $nav): string
{
    return View::make('camera/components/nav', [
        'camerasNav' => $nav,
        'cctvQuickActions' => cctv_quick_actions(),
    ], null);
}

function women_nav(array $nav): string
{
    return View::make('women-office/components/nav', [
        'womenNav' => $nav,
    ], null);
}

/**
 * @return list<array{label: string, path: string, variant: string}>
 */
function cctv_quick_actions(): array
{
    static $resolved = false;
    static $actions = [];

    if ($resolved) {
        return $actions;
    }

    $resolved = true;

    if (!hasPermission('cctv.log.create')) {
        return [];
    }

    $operatorId = Auth::id();
    if ($operatorId === null || $operatorId < 1) {
        return [];
    }

    $shift = (new \App\Services\Cctv\ShiftService())->findOpenForOperator($operatorId);
    if ($shift === null) {
        return [];
    }

    $actions = [
        ['label' => '+ Nueva Novedad', 'path' => '/cctv/log/create', 'variant' => 'primary'],
        ['label' => '+ Incidente', 'path' => '/cctv/log/incident/create', 'variant' => 'outline'],
    ];

    return $actions;
}

function cameras_nav(array $nav): string
{
    return cctv_nav($nav);
}

function cctv_can_edit_log_entry(array $record): bool
{
    return (new \App\Services\Cctv\ClosedShiftPolicy())->canEditLogEntry($record);
}

function cctv_can_cancel_log_entry(array $record): bool
{
    return (new \App\Services\Cctv\ClosedShiftPolicy())->canCancelLogEntry($record);
}

function cctv_can_edit_shift(array $shift): bool
{
    return (new \App\Services\Cctv\ClosedShiftPolicy())->canEditShift($shift);
}

function format_fecha_institucional(?int $timestamp = null): string
{
    $timestamp ??= time();
    $days = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    return sprintf(
        '%s %d de %s de %d',
        $days[(int) date('w', $timestamp)],
        (int) date('j', $timestamp),
        $months[(int) date('n', $timestamp) - 1],
        (int) date('Y', $timestamp)
    );
}

function permission_module_label(string $module): string
{
    return match ($module) {
        'dashboard' => 'Panel',
        'users' => 'Usuarios',
        'roles' => 'Roles',
        'permissions' => 'Permisos',
        'audit' => 'Auditoría',
        'settings' => 'Configuración',
        'sectors' => 'Sectores',
        'security' => 'Seguridad Municipal',
        'senda' => 'SENDA',
        'cameras' => 'Central de Cámaras',
        'women' => 'Oficina de la Mujer',
        'meetings' => 'Reuniones',
        'guards' => 'Guardias Municipales',
        'auth' => 'Autenticación',
        default => ucfirst($module),
    };
}

function audit_action_label(string $action): string
{
    return \App\Services\AuditService::actionLabel($action);
}

function audit_resource_label(?string $resource): string
{
    return \App\Services\AuditService::resourceLabel($resource);
}

function meetings_pending_signature_count(): int
{
    if (!hasPermission('meetings.view_pending_signatures')) {
        return 0;
    }

    return (new \App\Services\Meetings\MeetingSignatureService())->getPendingCountForUser();
}
