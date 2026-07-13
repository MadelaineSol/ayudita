<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\HttpException;
use App\Repositories\AuditRepository;
use App\Repositories\RefreshTokenRepository;
use App\Repositories\UserRepository;
use App\Security\Password;

/**
 * Recuperación segura de contraseña por token de un solo uso.
 * El envío real del email se delega a NotificationService/MailService.
 */
final class PasswordResetService
{
    private const TTL_MINUTES = 30;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly AuditRepository $audit = new AuditRepository(),
    ) {
    }

    /** Genera el token. Devuelve null si el email no existe (sin revelarlo). */
    public function requestReset(string $email): ?string
    {
        $user = $this->users->findByEmail(strtolower($email));
        if ($user === null) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        Database::connection()
            ->prepare('INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)')
            ->execute([
                strtolower($email),
                hash('sha256', $token),
                date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60),
            ]);
        return $token;
    }

    public function resetPassword(string $token, string $newPassword, string $ip): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM password_resets
              WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
              ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $reset = $stmt->fetch();

        if ($reset === false) {
            throw new HttpException('El enlace de recuperación no es válido o expiró', 400);
        }

        $user = $this->users->findByEmail($reset['email']);
        if ($user === null) {
            throw new HttpException('Cuenta no encontrada', 404);
        }

        $this->users->updateById((int) $user['id'], [
            'password_hash' => Password::hash($newPassword),
        ]);
        $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$reset['id']]);

        // Cerrar todas las sesiones activas por seguridad.
        (new RefreshTokenRepository())->revokeAllForUser((int) $user['id']);
        $this->audit->log((int) $user['id'], 'auth.password_reset', 'users', (int) $user['id'], $ip);
    }
}
