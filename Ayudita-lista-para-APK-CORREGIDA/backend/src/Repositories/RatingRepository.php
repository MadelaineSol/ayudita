<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Calificaciones bidireccionales entre clientes y prestadores.
 */
final class RatingRepository extends BaseRepository
{
    public function create(array $data): int
    {
        return $this->insert('ratings', $data);
    }

    public function existsForBooking(int $bookingId, int $raterId): bool
    {
        return $this->fetchOne(
            'SELECT id FROM ratings WHERE booking_id = ? AND rater_id = ?',
            [$bookingId, $raterId]
        ) !== null;
    }

    public function listForUser(int $ratedUserId, int $limit = 20): array
    {
        return $this->fetchAll(
            'SELECT r.stars, r.comment, r.created_at, u.name AS rater_name, u.avatar_url AS rater_avatar
               FROM ratings r
               JOIN users u ON u.id = r.rater_id
              WHERE r.rated_id = ?
              ORDER BY r.id DESC LIMIT ' . $limit,
            [$ratedUserId]
        );
    }
}
