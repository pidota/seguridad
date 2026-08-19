<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use Core\Session;

final class PersonContext
{
    public const SESSION_KEY = 'women_person_id';
    public const LOOKUP_RUT_KEY = 'women_person_lookup_rut';

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    public static function remember(int $id): void
    {
        Session::put(self::SESSION_KEY, $id);
    }

    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::LOOKUP_RUT_KEY);
    }

    public static function rememberLookupRut(string $formatted): void
    {
        Session::put(self::LOOKUP_RUT_KEY, $formatted);
    }

    public static function lookupRut(): ?string
    {
        $value = Session::get(self::LOOKUP_RUT_KEY);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
