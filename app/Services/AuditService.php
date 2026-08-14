<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;
use Core\Auth;
use Core\Request;

final class AuditService
{
    public const MODULE_SENDA = 'senda';
    public const MODULE_CCTV = 'cctv';

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_RESTORED = 'restored';
    public const ACTION_DRAFT_SAVED = 'draft_saved';
    public const ACTION_FINALIZED = 'finalized';
    public const ACTION_UPDATED_COMPLETED = 'updated_completed';
    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_NEXT_DATE_CHANGED = 'next_date_changed';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';
    public const ACTION_ACTIVATED = 'activated';
    public const ACTION_DEACTIVATED = 'deactivated';
    public const ACTION_PASSWORD_CHANGED = 'password_changed';
    public const ACTION_VIEW_PERSON_HISTORY = 'view_person_history';

    public const RESOURCE_PERSON = 'senda_people';
    public const RESOURCE_ATTENTION = 'senda_attentions';
    public const RESOURCE_REFERRAL = 'senda_assisted_referrals';
    public const RESOURCE_FOLLOW_UP = 'senda_follow_ups';
    public const RESOURCE_CAMERA_EVENT = 'camera_events';
    public const RESOURCE_CCTV_SHIFT = 'cctv_shifts';
    public const RESOURCE_CCTV_CAMERA = 'cctv_cameras';
    public const RESOURCE_CCTV_LOG_ENTRY = 'cctv_log_entries';

    /** @var list<string> */
    private const SENSITIVE = [
        'password',
        'password_confirmation',
        'current_password',
        '_token',
        '_method',
    ];

    public function __construct(private readonly AuditRepository $audits = new AuditRepository())
    {
    }

    public function log(
        string $action,
        string $module,
        ?string $resource = null,
        int|string|null $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $user = Auth::user();
        $request = Request::capture();

        $this->audits->insert([
            'user_id' => $user['id'] ?? null,
            'user_name' => $user['name'] ?? null,
            'action' => $action,
            'module' => $module,
            'resource' => $resource,
            'resource_id' => $resourceId !== null ? (string) $resourceId : null,
            'old_values' => $this->encode($oldValues),
            'new_values' => $this->encode($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function created(string $module, string $resource, int|string $id, array $newValues): void
    {
        $this->log(self::ACTION_CREATED, $module, $resource, $id, null, $newValues);
    }

    public function updated(string $module, string $resource, int|string $id, array $oldValues, array $newValues): void
    {
        if (self::same($oldValues, $newValues)) {
            return;
        }

        $this->log(self::ACTION_UPDATED, $module, $resource, $id, $oldValues, $newValues);
    }

    public function deleted(string $module, string $resource, int|string $id, array $oldValues): void
    {
        $this->log(self::ACTION_DELETED, $module, $resource, $id, $oldValues, null);
    }

    public function restored(string $module, string $resource, int|string $id, array $oldValues, array $newValues): void
    {
        $this->log(self::ACTION_RESTORED, $module, $resource, $id, $oldValues, $newValues);
    }

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            self::ACTION_CREATED => 'Creación',
            self::ACTION_UPDATED => 'Modificación',
            self::ACTION_DELETED => 'Eliminación',
            self::ACTION_RESTORED => 'Restauración',
            self::ACTION_DRAFT_SAVED => 'Guardado de borrador',
            self::ACTION_FINALIZED => 'Finalización',
            self::ACTION_UPDATED_COMPLETED => 'Modificación posterior',
            self::ACTION_CANCELLED => 'Anulación',
            self::ACTION_NEXT_DATE_CHANGED => 'Cambio de próxima fecha',
            self::ACTION_LOGIN => 'Inicio de sesión',
            self::ACTION_LOGOUT => 'Cierre de sesión',
            self::ACTION_LOGIN_FAILED => 'Inicio de sesión fallido',
            self::ACTION_ACTIVATED => 'Activación',
            self::ACTION_DEACTIVATED => 'Desactivación',
            self::ACTION_PASSWORD_CHANGED => 'Cambio de contraseña',
            self::ACTION_VIEW_PERSON_HISTORY => 'Consulta de historial SENDA',
        ];
    }

    public static function actionLabel(string $action): string
    {
        return self::actionLabels()[$action] ?? $action;
    }

    public static function resourceLabel(?string $resource): string
    {
        if ($resource === null || $resource === '') {
            return '—';
        }

        return match ($resource) {
            self::RESOURCE_PERSON => 'Persona',
            self::RESOURCE_ATTENTION => 'Atención',
            self::RESOURCE_REFERRAL => 'Ficha de referencia',
            self::RESOURCE_FOLLOW_UP => 'Seguimiento',
            self::RESOURCE_CAMERA_EVENT => 'Evento CCTV',
            self::RESOURCE_CCTV_SHIFT => 'Turno CCTV',
            self::RESOURCE_CCTV_CAMERA => 'Cámara CCTV',
            self::RESOURCE_CCTV_LOG_ENTRY => 'Entrada bitácora CCTV',
            'users' => 'Usuario',
            'roles' => 'Rol',
            default => $resource,
        };
    }

    /**
     * @param list<string> $keys
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function pick(array $row, array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }

        return $out;
    }

    public static function same(array $oldValues, array $newValues): bool
    {
        return json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            === json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function encode(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }

        $clean = $this->stripSensitive($values);

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function stripSensitive(array $values): array
    {
        foreach (self::SENSITIVE as $key) {
            unset($values[$key]);
        }

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->stripSensitive($value);
            }
        }

        if (isset($values['roles']) && is_array($values['roles'])) {
            $values['roles'] = array_values($values['roles']);
        }

        return $values;
    }
}
