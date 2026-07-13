<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;

/**
 * Canal unificado de notificaciones.
 * Hoy: notificaciones internas (in-app).
 * Extensible a Push (FCM/APNs), Email (SMTP) y SMS sin tocar a los llamadores.
 */
final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $repository = new NotificationRepository(),
    ) {
    }

    public function notify(int $userId, string $type, string $title, string $body, ?array $data = null): void
    {
        $this->repository->create($userId, $type, $title, $body, $data);
        // TODO producción: encolar push (FCM/APNs) y email según preferencias del usuario.
    }
}
