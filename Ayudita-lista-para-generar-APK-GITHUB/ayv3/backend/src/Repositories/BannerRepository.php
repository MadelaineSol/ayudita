<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Banners promocionales.
 */
final class BannerRepository extends BaseRepository
{
    public function allActive(): array
    {
        return $this->fetchAll(
            'SELECT id, title, image_url, link, emoji FROM banners WHERE active = 1 ORDER BY sort_order'
        );
    }

    public function all(): array
    {
        return $this->fetchAll('SELECT * FROM banners ORDER BY sort_order');
    }

    public function create(array $data): int
    {
        return $this->insert('banners', $data);
    }

    public function updateById(int $id, array $data): void
    {
        $this->update('banners', $data, 'id = :_id', ['_id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM banners WHERE id = ?', [$id]);
    }
}
