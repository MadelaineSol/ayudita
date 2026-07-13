<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Paginator;

/**
 * Registro de auditoría de acciones sensibles.
 */
final class AuditRepository extends BaseRepository
{
    public function log(?int $userId, string $action, ?string $entity = null, ?int $entityId = null, ?string $ip = null, ?array $details = null): void
    {
        $this->insert('audit_logs', [
            'user_id'   => $userId,
            'action'    => $action,
            'entity'    => $entity,
            'entity_id' => $entityId,
            'ip'        => $ip,
            'details'   => $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public function listAll(Paginator $paginator, ?string $action = null): array
    {
        $where = '';
        $params = [];
        if ($action !== null && $action !== '') {
            $where = 'WHERE a.action = ?';
            $params[] = $action;
        }
        return $this->fetchAll(
            "SELECT a.*, u.name AS user_name
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
              $where
              ORDER BY a.id DESC
              LIMIT {$paginator->perPage} OFFSET {$paginator->offset}",
            $params
        );
    }
}
