<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Conversaciones y mensajes del chat interno.
 */
final class ChatRepository extends BaseRepository
{
    public function findOrCreateConversation(int $userA, int $userB, ?int $bookingId): int
    {
        [$one, $two] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];
        $row = $this->fetchOne(
            'SELECT id FROM conversations
              WHERE user_one = ? AND user_two = ? AND (booking_id <=> ?)',
            [$one, $two, $bookingId]
        );
        if ($row !== null) {
            return (int) $row['id'];
        }
        return $this->insert('conversations', [
            'user_one'   => $one,
            'user_two'   => $two,
            'booking_id' => $bookingId,
        ]);
    }

    public function findConversation(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM conversations WHERE id = ?', [$id]);
    }

    public function listForUser(int $userId): array
    {
        return $this->fetchAll(
            'SELECT cv.id, cv.booking_id, cv.created_at,
                    IF(cv.user_one = ?, cv.user_two, cv.user_one) AS other_user_id,
                    u.name AS other_name, u.avatar_url AS other_avatar,
                    (SELECT m.body FROM messages m
                      WHERE m.conversation_id = cv.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                    (SELECT m.created_at FROM messages m
                      WHERE m.conversation_id = cv.id ORDER BY m.id DESC LIMIT 1) AS last_message_at,
                    (SELECT COUNT(*) FROM messages m
                      WHERE m.conversation_id = cv.id AND m.sender_id != ? AND m.read_at IS NULL) AS unread
               FROM conversations cv
               JOIN users u ON u.id = IF(cv.user_one = ?, cv.user_two, cv.user_one)
              WHERE cv.user_one = ? OR cv.user_two = ?
              ORDER BY last_message_at DESC',
            [$userId, $userId, $userId, $userId, $userId]
        );
    }

    public function messages(int $conversationId, int $afterId = 0, int $limit = 50): array
    {
        return $this->fetchAll(
            'SELECT id, sender_id, type, body, file_url, lat, lng, read_at, created_at
               FROM messages
              WHERE conversation_id = ? AND id > ?
              ORDER BY id ASC
              LIMIT ' . $limit,
            [$conversationId, $afterId]
        );
    }

    public function addMessage(array $data): int
    {
        return $this->insert('messages', $data);
    }

    public function markRead(int $conversationId, int $readerId): void
    {
        $this->execute(
            'UPDATE messages SET read_at = NOW()
              WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL',
            [$conversationId, $readerId]
        );
    }
}
