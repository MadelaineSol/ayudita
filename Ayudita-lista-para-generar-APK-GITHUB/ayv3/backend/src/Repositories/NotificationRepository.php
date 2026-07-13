<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Notificaciones internas de la aplicación.
 */
final class NotificationRepository extends BaseRepository
{
    public function create(int $userId, string $type, string $title, string $body, ?array $data = null): int
    {
        return $this->insert('notifications', [
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public function listForUser(int $userId, int $limit = 50): array
    {
        return $this->fetchAll(
            'SELECT id, type, title, body, data, read_at, created_at
               FROM notifications WHERE user_id = ?
              ORDER BY id DESC LIMIT ' . $limit,
            [$userId]
        );
    }

    public function unreadCount(int $userId): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
        return (int) $row['total'];
    }

    public function markAllRead(int $userId): void
    {
        $this->execute('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }
}
