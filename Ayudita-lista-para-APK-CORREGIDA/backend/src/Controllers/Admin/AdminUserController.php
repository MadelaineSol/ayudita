<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Helpers\Paginator;
use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;
use App\Validation\Validator;

/**
 * Administración de usuarios (clientes y prestadores).
 */
final class AdminUserController
{
    public function index(Request $request): void
    {
        $paginator = new Paginator($request->queryParam('page'));
        $where = ['u.deleted_at IS NULL'];
        $params = [];

        if ($role = $request->queryParam('role')) {
            $where[] = 'u.role = :role';
            $params['role'] = $role;
        }
        if ($status = $request->queryParam('status')) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }
        if ($q = $request->queryParam('q')) {
            $where[] = '(u.name LIKE :q OR u.email LIKE :q2)';
            $params['q'] = "%$q%";
            $params['q2'] = "%$q%";
        }

        $whereSql = implode(' AND ', $where);
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT u.id, u.role, u.name, u.email, u.phone, u.status, u.city,
                    u.email_verified_at, u.created_at,
                    pp.verified AS provider_verified, pp.balance, pp.rating_avg
               FROM users u
               LEFT JOIN provider_profiles pp ON pp.user_id = u.id
              WHERE $whereSql
              ORDER BY u.created_at DESC
              LIMIT {$paginator->perPage} OFFSET {$paginator->offset}"
        );
        $stmt->execute($params);

        $count = $db->prepare("SELECT COUNT(*) AS t FROM users u WHERE $whereSql");
        $count->execute($params);

        Response::json($stmt->fetchAll(), 200, $paginator->meta((int) $count->fetch()['t']));
    }

    public function update(Request $request): void
    {
        $users = new UserRepository();
        $user = $users->findById((int) $request->param('id'));
        if ($user === null) {
            throw new HttpException('Usuario no encontrado', 404);
        }

        $data = Validator::validate($request->body, [
            'status'   => 'in:active,pending,blocked',
            'verified' => 'boolean', // verificación de prestador
        ]);

        if (isset($data['status'])) {
            $users->updateById((int) $user['id'], ['status' => $data['status']]);
        }
        if (isset($data['verified']) && $user['role'] === 'provider') {
            Database::connection()
                ->prepare('UPDATE provider_profiles SET verified = ? WHERE user_id = ?')
                ->execute([(int) (bool) $data['verified'], (int) $user['id']]);
        }

        (new AuditRepository())->log(
            $request->userId(), 'admin.user_update', 'users', (int) $user['id'], $request->ip, $data
        );
        Response::json($users->publicProfile($users->findById((int) $user['id'])));
    }

    public function destroy(Request $request): void
    {
        $users = new UserRepository();
        $user = $users->findById((int) $request->param('id'));
        if ($user === null) {
            throw new HttpException('Usuario no encontrado', 404);
        }
        if ($user['role'] === 'admin') {
            throw new HttpException('No se puede eliminar un administrador', 403);
        }
        $users->updateById((int) $user['id'], ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'blocked']);
        (new AuditRepository())->log($request->userId(), 'admin.user_delete', 'users', (int) $user['id'], $request->ip);
        Response::json(['message' => 'Usuario eliminado (soft delete)']);
    }
}
