<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Paginación estándar para listados.
 */
final class Paginator
{
    public readonly int $page;
    public readonly int $perPage;
    public readonly int $offset;

    public function __construct(mixed $page, mixed $perPage = 20, int $maxPerPage = 50)
    {
        $this->page = max(1, (int) $page);
        $this->perPage = min($maxPerPage, max(1, (int) $perPage));
        $this->offset = ($this->page - 1) * $this->perPage;
    }

    public function meta(int $total): array
    {
        return [
            'page'        => $this->page,
            'per_page'    => $this->perPage,
            'total'       => $total,
            'total_pages' => (int) ceil($total / $this->perPage),
        ];
    }
}
