<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Categorías de servicios.
 */
final class CategoryRepository extends BaseRepository
{
    public function allActive(): array
    {
        return $this->fetchAll(
            'SELECT id, name, slug, icon, description
               FROM categories
              WHERE active = 1 AND deleted_at IS NULL
              ORDER BY sort_order, name'
        );
    }

    public function allForAdmin(): array
    {
        return $this->fetchAll('SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name');
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public function create(array $data): int
    {
        return $this->insert('categories', $data);
    }

    public function updateById(int $id, array $data): void
    {
        $this->update('categories', $data, 'id = :_id', ['_id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->execute('UPDATE categories SET deleted_at = NOW(), active = 0 WHERE id = ?', [$id]);
    }
}
