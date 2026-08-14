<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validación y normalización de RUT chileno.
 * Acepta formatos como 12.345.678-5 y 12345678-5.
 */
final class ChileanRutValidator
{
    public static function isValid(string $value): bool
    {
        return self::normalize($value) !== null;
    }

    /**
     * Cuerpo + dígito verificador, sin puntos ni guion (ej. 123456785).
     */
    public static function normalize(string $value): ?string
    {
        $clean = self::clean($value);

        if ($clean === null || !self::matchesVerifier($clean)) {
            return null;
        }

        return $clean;
    }

    public static function format(string $value): ?string
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            return null;
        }

        $body = substr($normalized, 0, -1);
        $verifier = substr($normalized, -1);

        return self::thousands($body) . '-' . $verifier;
    }

    /**
     * Quita puntos, espacios y guion. No comprueba el dígito verificador.
     */
    public static function clean(string $value): ?string
    {
        $value = strtoupper(trim($value));
        $value = str_replace(['.', ' ', '-'], '', $value);

        if (preg_match('/^(\d{7,8})([0-9K])$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private static function matchesVerifier(string $clean): bool
    {
        $body = substr($clean, 0, -1);
        $verifier = substr($clean, -1);

        return $verifier === self::verifierFor($body);
    }

    private static function verifierFor(string $body): string
    {
        $sum = 0;
        $factor = 2;

        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += (int) $body[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $rest = 11 - ($sum % 11);

        return match ($rest) {
            11 => '0',
            10 => 'K',
            default => (string) $rest,
        };
    }

    private static function thousands(string $body): string
    {
        $reversed = strrev($body);
        $chunks = str_split($reversed, 3);

        return strrev(implode('.', $chunks));
    }
}
