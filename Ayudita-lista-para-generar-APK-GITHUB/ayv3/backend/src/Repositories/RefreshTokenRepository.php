<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Refresh tokens rotativos, almacenados como hash SHA-256.
 */
final class RefreshTokenRepository extends BaseRepository
{
    public function store(int $userId, string $plainToken, int $ttlDays, ?string $userAgent, string $ip): void
    {
        $this->insert('refresh_tokens', [
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $plainToken),
            'user_agent' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
            'ip'         => $ip,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlDays * 86400),
        ]);
    }

    public function findValid(string $plainToken): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM refresh_tokens
              WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > NOW()',
            [hash('sha256', $plainToken)]
        );
    }

    public function revoke(string $plainToken): void
    {
        $this->execute(
            'UPDATE refresh_tokens SET revoked_at = NOW() WHERE token_hash = ?',
            [hash('sha256', $plainToken)]
        );
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->execute(
            'UPDATE refresh_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL',
            [$userId]
        );
    }
}
