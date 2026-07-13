<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\NotificationRepository;

/**
 * Notificaciones internas del usuario.
 */
final class NotificationController
{
    public function index(Request $request): void
    {
        $repo = new NotificationRepository();
        Response::json([
            'items'  => $repo->listForUser($request->userId()),
            'unread' => $repo->unreadCount($request->userId()),
        ]);
    }

    public function markRead(Request $request): void
    {
        (new NotificationRepository())->markAllRead($request->userId());
        Response::json(['message' => 'Notificaciones leídas']);
    }
}
