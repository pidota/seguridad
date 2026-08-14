<?php

declare(strict_types=1);

namespace App\Services\Senda;

use Core\Request;
use Core\Session;

/**
 * Contexto de trabajo del tipo de ingreso.
 * La sesión y la URL sirven solo para la interfaz; el valor permanente vive en la atención.
 */
final class EntryTypeContext
{
    public const SESSION_KEY = 'senda_entry_type';
    public const QUERY_KEY = 'ingreso';

    public static function current(): ?string
    {
        $fromQuery = (string) Request::capture()->query(self::QUERY_KEY, '');

        if (EntryType::isValid($fromQuery)) {
            return $fromQuery;
        }

        $fromSession = (string) Session::get(self::SESSION_KEY, '');

        if (EntryType::isValid($fromSession)) {
            return $fromSession;
        }

        return null;
    }

    public static function remember(string $type): void
    {
        if (!EntryType::isValid($type)) {
            return;
        }

        Session::put(self::SESSION_KEY, $type);
    }

    public static function meta(): ?array
    {
        $type = self::current();

        return $type === null ? null : EntryType::meta($type);
    }

    public static function resolveForStore(?string $submitted): ?string
    {
        $submitted = trim((string) $submitted);

        if (EntryType::isValid($submitted)) {
            return $submitted;
        }

        return self::current();
    }
}
