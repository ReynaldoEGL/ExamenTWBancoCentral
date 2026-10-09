<?php

namespace App\Support;

/** Normaliza booleanos PostgreSQL obtenidos por Query Builder (true/false o t/f). */
final class PgBoolean
{
    public static function value(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 't', 'true', 'yes', 'on'], true);
    }
}
