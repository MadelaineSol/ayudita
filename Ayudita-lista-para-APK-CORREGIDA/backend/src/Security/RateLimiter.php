<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Database;

/**
 * Rate limiting por ventana fija almacenado en MySQL.
 * Protege endpoints sensibles (login, registro, etc.).
 */
final class RateLimiter
{
    /**
     * @return bool true si la petición está permitida.
     */
    public static function attempt(string $key, int $maxHits, int $windowSeconds): bool
    {
        $db = Database::connection();
        $now = time();

        $stmt = $db->prepare('SELECT hits, window_start FROM rate_limits WHERE rate_key = ? FOR UPDATE');
        $db->beginTransaction();
        try {
            $stmt->execute([$key]);
            $row = $stmt->fetch();

            if ($row === false || ($now - (int) $row['window_start']) >= $windowSeconds) {
                $db->prepare(
                    'REPLACE INTO rate_limits (rate_key, hits, window_start) VALUES (?, 1, ?)'
                )->execute([$key, $now]);
                $db->commit();
                return true;
            }

            if ((int) $row['hits'] >= $maxHits) {
                $db->commit();
                return false;
            }

            $db->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE rate_key = ?')->execute([$key]);
            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            return true; // Ante un fallo del limitador, no bloquear el servicio.
        }
    }
}
