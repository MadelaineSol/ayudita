<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Solicitud de contratación ya validada.
 */
final class CreateBookingDTO
{
    public function __construct(
        public readonly int $providerId,
        public readonly int $categoryId,
        public readonly string $unit,
        public readonly int $quantity,
        public readonly string $startAt,
        public readonly ?string $description,
        public readonly ?string $address,
        public readonly ?float $lat,
        public readonly ?float $lng,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['provider_id'],
            (int) $data['category_id'],
            $data['unit'],
            (int) $data['quantity'],
            $data['start_at'],
            $data['description'] ?? null,
            $data['address'] ?? null,
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
        );
    }
}
