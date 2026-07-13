<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Helpers\Paginator;
use App\Repositories\AuditRepository;
use App\Repositories\DisputeRepository;
use App\Repositories\SettingRepository;
use App\Validation\Validator;

/**
 * Configuración global (incluida la comisión), disputas y logs de auditoría.
 */
final class AdminSettingsController
{
    /** Claves de configuración editables desde el panel. */
    private const EDITABLE = [
        'app_name', 'commission_percent', 'tax_percent', 'currency',
        'min_withdrawal', 'support_email',
    ];

    public function settings(Request $request): void
    {
        Response::json((new SettingRepository())->all());
    }

    public function updateSettings(Request $request): void
    {
        $repo = new SettingRepository();
        $updated = [];
        foreach ($request->body as $key => $value) {
            if (!in_array($key, self::EDITABLE, true)) {
                continue;
            }
            if (in_array($key, ['commission_percent', 'tax_percent'], true)) {
                $number = (float) $value;
                if ($number < 0 || $number > 100) {
                    throw new HttpException("$key debe estar entre 0 y 100", 422);
                }
                $value = (string) $number;
            }
            $repo->set($key, substr((string) $value, 0, 500));
            $updated[$key] = $value;
        }

        (new AuditRepository())->log(
            $request->userId(), 'admin.settings_update', 'settings', null, $request->ip, $updated
        );
        Response::json($repo->all());
    }

    public function disputes(Request $request): void
    {
        $paginator = new Paginator($request->queryParam('page'));
        Response::json((new DisputeRepository())->listAll($request->queryParam('status'), $paginator));
    }

    public function resolveDispute(Request $request): void
    {
        $repo = new DisputeRepository();
        $dispute = $repo->findById((int) $request->param('id'));
        if ($dispute === null) {
            throw new HttpException('Disputa no encontrada', 404);
        }
        if ($dispute['status'] !== 'open') {
            throw new HttpException('La disputa ya fue procesada', 409);
        }

        $data = Validator::validate($request->body, [
            'status'     => 'required|in:resolved,rejected',
            'resolution' => 'required|min:5|max:500',
        ]);

        $repo->updateById((int) $dispute['id'], [
            'status'      => $data['status'],
            'resolution'  => \App\Helpers\Text::clean($data['resolution']),
            'resolved_by' => $request->userId(),
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditRepository())->log(
            $request->userId(), 'admin.dispute_' . $data['status'], 'disputes', (int) $dispute['id'], $request->ip
        );
        Response::json($repo->findById((int) $dispute['id']));
    }

    public function logs(Request $request): void
    {
        $paginator = new Paginator($request->queryParam('page'), 50);
        Response::json((new AuditRepository())->listAll($paginator, $request->queryParam('action')));
    }
}
