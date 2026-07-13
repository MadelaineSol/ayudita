<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\CreateBookingDTO;
use App\Exceptions\HttpException;
use App\Helpers\Text;
use App\Repositories\BookingRepository;
use App\Repositories\ProviderRepository;

/**
 * Lógica de negocio de contrataciones: creación, máquina de estados,
 * extensiones y cancelaciones.
 */
final class BookingService
{
    /** Transiciones de estado permitidas y quién puede ejecutarlas. */
    private const TRANSITIONS = [
        'accept'   => ['from' => ['pending'],               'to' => 'accepted',    'by' => 'provider'],
        'reject'   => ['from' => ['pending'],               'to' => 'cancelled',   'by' => 'provider'],
        'on_way'   => ['from' => ['accepted'],              'to' => 'on_way',      'by' => 'provider'],
        'start'    => ['from' => ['accepted', 'on_way'],    'to' => 'in_progress', 'by' => 'provider'],
        'complete' => ['from' => ['in_progress'],           'to' => 'completed',   'by' => 'provider'],
    ];

    public function __construct(
        private readonly BookingRepository $bookings = new BookingRepository(),
        private readonly ProviderRepository $providers = new ProviderRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    public function create(int $clientId, CreateBookingDTO $dto): array
    {
        $provider = $this->providers->findById($dto->providerId);
        if ($provider === null || !$provider['available']) {
            throw new HttpException('El prestador no está disponible', 404);
        }
        if ($provider['user_id'] === $clientId) {
            throw new HttpException('No podés contratarte a vos mismo', 400);
        }

        $rate = $this->rateFor($provider, $dto->unit);
        $amount = round($rate * $dto->quantity, 2);
        $endAt = $this->computeEndAt($dto->startAt, $dto->unit, $dto->quantity);

        $bookingId = $this->bookings->create([
            'code'         => strtoupper(substr(bin2hex(random_bytes(6)), 0, 10)),
            'client_id'    => $clientId,
            'provider_id'  => $dto->providerId,
            'category_id'  => $dto->categoryId,
            'unit'         => $dto->unit,
            'quantity'     => $dto->quantity,
            'rate'         => $rate,
            'amount_total' => $amount,
            'description'  => $dto->description !== null ? Text::clean($dto->description) : null,
            'address'      => $dto->address !== null ? Text::clean($dto->address) : null,
            'lat'          => $dto->lat,
            'lng'          => $dto->lng,
            'start_at'     => date('Y-m-d H:i:s', strtotime($dto->startAt)),
            'end_at'       => $endAt,
        ]);
        $this->bookings->updateStatus($bookingId, 'pending', $clientId);

        $this->notifications->notify(
            (int) $provider['user_id'],
            'booking.new',
            '¡Nueva solicitud de trabajo! 🎉',
            'Tenés una nueva solicitud de contratación. Entrá para aceptarla o rechazarla.',
            ['booking_id' => $bookingId]
        );

        return $this->bookings->findById($bookingId);
    }

    /** Ejecuta una transición de la máquina de estados. */
    public function transition(array $booking, string $action, array $actor): array
    {
        $rule = self::TRANSITIONS[$action] ?? null;
        if ($rule === null) {
            throw new HttpException('Acción desconocida', 400);
        }
        if (!in_array($booking['status'], $rule['from'], true)) {
            throw new HttpException('El trabajo no está en un estado válido para esta acción', 409);
        }
        $this->assertActor($booking, $actor, $rule['by']);

        $this->bookings->updateStatus((int) $booking['id'], $rule['to'], (int) $actor['id']);

        if ($rule['to'] === 'completed') {
            // El saldo se acredita recién cuando el admin aprueba el payout.
            $this->incrementJobsDone((int) $booking['provider_id']);
        }

        $counterpartId = (int) $actor['id'] === (int) $booking['client_id']
            ? (int) $booking['provider_user_id']
            : (int) $booking['client_id'];

        $this->notifications->notify(
            $counterpartId,
            'booking.' . $rule['to'],
            $this->statusTitle($rule['to']),
            'El trabajo ' . $booking['code'] . ' cambió de estado.',
            ['booking_id' => (int) $booking['id'], 'status' => $rule['to']]
        );

        return $this->bookings->findById((int) $booking['id']);
    }

    public function cancel(array $booking, array $actor, ?string $reason): array
    {
        if (in_array($booking['status'], ['completed', 'cancelled'], true)) {
            throw new HttpException('El trabajo ya finalizó', 409);
        }
        $isClient = (int) $booking['client_id'] === (int) $actor['id'];
        $isProvider = (int) $booking['provider_user_id'] === (int) $actor['id'];
        if (!$isClient && !$isProvider && $actor['role'] !== 'admin') {
            throw new HttpException('No podés cancelar este trabajo', 403);
        }

        $this->bookings->updateStatus((int) $booking['id'], 'cancelled', (int) $actor['id'], [
            'cancel_reason' => $reason !== null ? Text::clean($reason) : null,
        ]);
        return $this->bookings->findById((int) $booking['id']);
    }

    public function extend(array $booking, int $clientId, int $extraQuantity): array
    {
        if ((int) $booking['client_id'] !== $clientId) {
            throw new HttpException('Solo el cliente puede extender la contratación', 403);
        }
        if (!in_array($booking['status'], ['accepted', 'in_progress'], true)) {
            throw new HttpException('Solo se puede extender un trabajo aceptado o en curso', 409);
        }

        $amount = round((float) $booking['rate'] * $extraQuantity, 2);
        $newEndAt = $this->computeEndAt($booking['end_at'], $booking['unit'], $extraQuantity);

        $this->bookings->addExtension((int) $booking['id'], $extraQuantity, $amount, $newEndAt);
        $this->bookings->updateById((int) $booking['id'], [
            'quantity'     => (int) $booking['quantity'] + $extraQuantity,
            'amount_total' => (float) $booking['amount_total'] + $amount,
            'end_at'       => $newEndAt,
            'payment_status' => 'unpaid', // la extensión genera un nuevo saldo a pagar
        ]);

        $this->notifications->notify(
            (int) $booking['provider_user_id'],
            'booking.extended',
            'Contratación extendida ⏰',
            'El cliente extendió el trabajo ' . $booking['code'] . '.',
            ['booking_id' => (int) $booking['id']]
        );

        return $this->bookings->findById((int) $booking['id']);
    }

    private function rateFor(array $provider, string $unit): float
    {
        return match ($unit) {
            'hour'  => (float) $provider['rate_hour'],
            'day'   => (float) $provider['rate_day'],
            'week'  => (float) $provider['rate_day'] * 5,
            'month' => (float) $provider['rate_day'] * 22,
            default => throw new HttpException('Unidad de contratación inválida', 400),
        };
    }

    private function computeEndAt(string $startAt, string $unit, int $quantity): string
    {
        $seconds = match ($unit) {
            'hour'  => 3600,
            'day'   => 86400,
            'week'  => 604800,
            'month' => 2592000,
        };
        return date('Y-m-d H:i:s', strtotime($startAt) + $seconds * $quantity);
    }

    private function assertActor(array $booking, array $actor, string $requiredParty): void
    {
        $ok = match ($requiredParty) {
            'provider' => (int) $booking['provider_user_id'] === (int) $actor['id'],
            'client'   => (int) $booking['client_id'] === (int) $actor['id'],
            default    => false,
        };
        if (!$ok && $actor['role'] !== 'admin') {
            throw new HttpException('No tenés permisos sobre este trabajo', 403);
        }
    }

    private function incrementJobsDone(int $providerId): void
    {
        \App\Core\Database::connection()
            ->prepare('UPDATE provider_profiles SET jobs_done = jobs_done + 1 WHERE id = ?')
            ->execute([$providerId]);
    }

    private function statusTitle(string $status): string
    {
        return match ($status) {
            'accepted'    => '¡Tu solicitud fue aceptada! ✅',
            'cancelled'   => 'El trabajo fue cancelado 😔',
            'on_way'      => 'El prestador está en camino 🚶',
            'in_progress' => 'El trabajo comenzó 💪',
            'completed'   => 'El trabajo finalizó 🎉',
            default       => 'Tu trabajo cambió de estado',
        };
    }
}
