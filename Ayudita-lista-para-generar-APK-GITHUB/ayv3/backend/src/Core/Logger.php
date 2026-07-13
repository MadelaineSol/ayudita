<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Logger de archivo simple con niveles y rotación diaria.
 */
final class Logger
{
    private static function write(string $level, string $message): void
    {
        $file = BASE_PATH . '/storage/logs/' . date('Y-m-d') . '.log';
        $line = sprintf("[%s] %s: %s\n", date('c'), strtoupper($level), $message);
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message): void
    {
        self::write('info', $message);
    }

    public static function warning(string $message): void
    {
        self::write('warning', $message);
    }

    public static function error(string $message): void
    {
        self::write('error', $message);
    }
}
