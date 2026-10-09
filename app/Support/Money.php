<?php

namespace App\Support;

/** Conversión exacta de importes MXN con dos decimales a centavos enteros. */
final class Money
{
    public static function toCents(string|int|float $amount): int
    {
        $value = trim((string) $amount);
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new \InvalidArgumentException('El importe debe tener como máximo dos decimales.');
        }

        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 2, '0');
        return ($whole * 100) + $fraction;
    }

    public static function fromCents(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
