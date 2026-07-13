<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Paginator;

/**
 * Contrataciones (bookings) y su historial de estados.
 */
final class BookingRepository extends BaseRepository
{
    private const BASE_SELECT = '
        SELECT b.*, c.name AS category_name, c.icon AS category_icon,
               uc.name AS client_name, uc.avatar_url AS client_avatar,
               up.name AS provider_name, up.avatar_url AS provider_avatar,
               pp.user_id AS provider_user_id
          FROM bookings b
          JOIN categories c        ON c.id = b.category_id
          JOIN users uc            ON uc.id = b.client_id
          JOIN provider_profiles pp ON pp.id = b.provider_id
          JOIN users up            ON up.id = pp.user_id';

    public function create(array $data): int
    {
        return $this->insert('bookings', $data);
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne(self::BASE_SELECT . ' WHERE b.id = ?', [$id]);
    }

    public function listForUser(int $userId, string $role, ?string $status, Paginator $paginator): array
    {
        $where = $role === 'provider'
            ? 'pp.user_id = :uid'
            : 'b.client_id = :uid';
        $params = ['uid' => $userId];
        if ($status !== null && $status !== '') {
            $where .= ' AND b.status = :status';
            $params['status'] = $status;
        }
        $items = $this->fetchAll(
            self::BASE_SELECT . " WHERE $where ORDER BY b.created_at DESC
             LIMIT {$paginator->perPage} OFFSET {$paginator->offset}",
            $params
        );
        $total = (int) $this->fetchOne(
            "SELECT COUNT(*) AS total FROM bookings b
               JOIN provider_profiles pp ON pp.id = b.provider_id
              WHERE $where",
            $params
        )['total'];
        return ['items' => $items, 'total' => $total];
    }

    public function updateStatus(int $id, string $status, ?int $changedBy, array $extra = []): void
    {
        $this->update('bookings', array_merge(['status' => $status], $extra), 'id = :_id', ['_id' => $id]);
        $this->insert('booking_status_history', [
            'booking_id' => $id,
            'status'     => $status,
            'changed_by' => $changedBy,
        ]);
    }

    public function updateById(int $id, array $data): void
    {
        $this->update('bookings', $data, 'id = :_id', ['_id' => $id]);
    }

    public function statusHistory(int $bookingId): array
    {
        return $this->fetchAll(
            'SELECT status, changed_by, created_at FROM booking_status_history
              WHERE booking_id = ? ORDER BY id',
            [$bookingId]
        );
    }

    public function addExtension(int $bookingId, int $extraQuantity, float $amount, string $newEndAt): int
    {
        return $this->insert('booking_extensions', [
            'booking_id'     => $bookingId,
            'extra_quantity' => $extraQuantity,
            'amount'         => $amount,
            'new_end_at'     => $newEndAt,
        ]);
    }

    public function extensions(int $bookingId): array
    {
        return $this->fetchAll(
            'SELECT extra_quantity, amount, new_end_at, created_at
               FROM booking_extensions WHERE booking_id = ? ORDER BY id',
            [$bookingId]
        );
    }
}
