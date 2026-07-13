<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Paginator;

/**
 * Disputas sobre contrataciones.
 */
final class DisputeRepository extends BaseRepository
{
    public function create(array $data): int
    {
        return $this->insert('disputes', $data);
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM disputes WHERE id = ?', [$id]);
    }

    public function listAll(?string $status, Paginator $paginator): array
    {
        $where = '';
        $params = [];
        if ($status !== null && $status !== '') {
            $where = 'WHERE d.status = ?';
            $params[] = $status;
        }
        return $this->fetchAll(
            "SELECT d.*, b.code AS booking_code, u.name AS opened_by_name
               FROM disputes d
               JOIN bookings b ON b.id = d.booking_id
               JOIN users u ON u.id = d.opened_by
              $where
              ORDER BY d.created_at DESC
              LIMIT {$paginator->perPage} OFFSET {$paginator->offset}",
            $params
        );
    }

    public function updateById(int $id, array $data): void
    {
        $this->update('disputes', $data, 'id = :_id', ['_id' => $id]);
    }
}
