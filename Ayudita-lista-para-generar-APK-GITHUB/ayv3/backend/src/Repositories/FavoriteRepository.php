<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Prestadores favoritos de cada usuario.
 */
final class FavoriteRepository extends BaseRepository
{
    public function add(int $userId, int $providerId): void
    {
        $this->execute(
            'INSERT IGNORE INTO favorites (user_id, provider_id) VALUES (?, ?)',
            [$userId, $providerId]
        );
    }

    public function remove(int $userId, int $providerId): void
    {
        $this->execute('DELETE FROM favorites WHERE user_id = ? AND provider_id = ?', [$userId, $providerId]);
    }

    public function listForUser(int $userId): array
    {
        return $this->fetchAll(
            'SELECT pp.id, u.name, u.avatar_url, u.city, pp.rate_hour, pp.rating_avg,
                    pp.rating_count, pp.verified, f.created_at AS favorited_at
               FROM favorites f
               JOIN provider_profiles pp ON pp.id = f.provider_id
               JOIN users u ON u.id = pp.user_id
              WHERE f.user_id = ? AND pp.deleted_at IS NULL
              ORDER BY f.created_at DESC',
            [$userId]
        );
    }

    public function ids(int $userId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['provider_id'],
            $this->fetchAll('SELECT provider_id FROM favorites WHERE user_id = ?', [$userId])
        );
    }
}
