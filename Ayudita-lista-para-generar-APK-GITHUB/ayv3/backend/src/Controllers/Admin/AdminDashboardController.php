<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ReportRepository;

/**
 * Dashboard y reportes del panel de administración.
 */
final class AdminDashboardController
{
    public function index(Request $request): void
    {
        Response::json((new ReportRepository())->dashboard());
    }

    public function report(Request $request): void
    {
        $repo = new ReportRepository();
        $type = (string) $request->param('type');
        $data = match ($type) {
            'revenue'        => $repo->revenueByMonth(),
            'top-categories' => $repo->topCategories(),
            'top-providers'  => $repo->topProviders(),
            'top-clients'    => $repo->topClients(),
            'active-users'   => $repo->activeUsersByMonth(),
            'heatmap'        => $repo->bookingHeatmap(),
            default          => null,
        };
        if ($data === null) {
            Response::error('Reporte desconocido', 404);
        }
        Response::json($data);
    }
}
