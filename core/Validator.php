<?php

declare(strict_types=1);

namespace Core;

class Validator
{
    protected array $errors = [];
    protected array $data = [];

    private array $messages = [
        'required' => 'El campo :field es obligatorio.',
        'email' => 'El campo :field debe ser un correo válido.',
        'min' => 'El campo :field debe tener al menos :param caracteres.',
        'max' => 'El campo :field no debe superar :param caracteres.',
        'confirmed' => 'La confirmación de :field no coincide.',
        'same' => 'El campo :field debe coincidir con :param.',
        'numeric' => 'El campo :field debe ser numérico.',
        'integer' => 'El campo :field debe ser un número entero.',
        'in' => 'El valor seleccionado en :field no es válido.',
        'alpha_dash' => 'El campo :field solo permite letras, números, guiones y guiones bajos.',
        'array' => 'El campo :field debe ser una lista de valores.',
        'boolean' => 'El campo :field no es válido.',
        'date' => 'El campo :field no es una fecha válida.',
        'time' => 'El campo :field no es una hora válida.',
    ];

    private array $attributes = [
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'current_password' => 'contraseña actual',
        'name' => 'nombre',
        'role_ids' => 'roles',
        'permission_ids' => 'permisos',
        'slug' => 'identificador',
        'is_active' => 'estado',
    ];

    public function validate(array $data, array $rules, array $attributes = []): bool
    {
        $this->data = $data;
        $this->errors = [];
        $this->attributes = array_merge($this->attributes, $attributes);

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $data[$field] ?? null;
            $nullable = in_array('nullable', $ruleList, true);

            if ($nullable && ($value === null || $value === '')) {
                continue;
            }

            foreach ($ruleList as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                $this->apply($field, $value, $rule);
            }
        }

        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstErrors(): array
    {
        $first = [];

        foreach ($this->errors as $field => $messages) {
            $first[$field] = $messages[0];
        }

        return $first;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    private function apply(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        $valid = match ($name) {
            'required' => $this->isFilled($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'min' => is_array($value)
                ? count($value) >= (int) $param
                : (is_string($value) && mb_strlen($value) >= (int) $param),
            'max' => is_array($value)
                ? count($value) <= (int) $param
                : (is_string($value) && mb_strlen($value) <= (int) $param),
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value,
            'same' => ($this->data[(string) $param] ?? null) === $value,
            'numeric' => is_numeric($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'in' => in_array((string) $value, explode(',', (string) $param), true),
            'alpha_dash' => is_string($value) && preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1,
            'array' => is_array($value),
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off'], true),
            'date' => $this->isDate($value),
            'time' => $this->isTime($value),
            default => throw new \InvalidArgumentException("Regla de validación desconocida: {$name}"),
        };

        if (!$valid) {
            $this->addError($field, $name, (string) $param);
        }
    }

    private function isFilled(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && trim((string) $value) !== '';
    }

    private function isDate(mixed $value): bool
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    private function isTime(mixed $value): bool
    {
        if (!is_string($value) || preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value) !== 1) {
            return false;
        }

        return true;
    }

    private function addError(string $field, string $rule, string $param): void
    {
        $label = $this->attributes[$field] ?? str_replace('_', ' ', $field);
        $message = $this->messages[$rule] ?? 'El campo :field no es válido.';
        $message = str_replace([':field', ':param'], [$label, $param], $message);

        $this->errors[$field][] = $message;
    }
}
