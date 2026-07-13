<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Utilidades de texto y saneamiento.
 */
final class Text
{
    /** Escapa HTML para prevenir XSS almacenado al reflejar contenido. */
    public static function clean(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    public static function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT', mb_strtolower(trim($value))) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    public static function randomCode(int $digits = 6): string
    {
        return str_pad((string) random_int(0, (10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
    }
}
