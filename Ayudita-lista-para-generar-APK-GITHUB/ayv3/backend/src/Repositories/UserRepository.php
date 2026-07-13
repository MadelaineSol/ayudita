<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Acceso a datos de usuarios.
 */
final class UserRepository extends BaseRepository
{
    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
    }

    public function create(array $data): int
    {
        return $this->insert('users', $data);
    }

    public function updateById(int $id, array $data): void
    {
        $this->update('users', $data, 'id = :_id', ['_id' => $id]);
    }

    public function registerFailedAttempt(int $id, int $maxAttempts, int $lockMinutes): void
    {
        $this->execute(
            'UPDATE users
                SET failed_attempts = failed_attempts + 1,
                    locked_until = IF(failed_attempts + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), locked_until)
              WHERE id = ?',
            [$maxAttempts, $lockMinutes, $id]
        );
    }

    public function resetFailedAttempts(int $id): void
    {
        $this->execute('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?', [$id]);
    }

    /** Datos públicos y seguros de un usuario (para exponer en la API). */
    public function publicProfile(array $user): array
    {
        return [
            'id'         => (int) $user['id'],
            'role'       => $user['role'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'phone'      => $user['phone'],
            'avatar_url' => $user['avatar_url'],
            'address'    => $user['address'],
            'city'       => $user['city'],
            'lat'        => $user['lat'] !== null ? (float) $user['lat'] : null,
            'lng'        => $user['lng'] !== null ? (float) $user['lng'] : null,
            'status'     => $user['status'],
            'verified_email' => $user['email_verified_at'] !== null,
            'created_at' => $user['created_at'],
        ];
    }
}
