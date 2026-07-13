<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Consultas agregadas para el dashboard y los reportes del panel admin.
 */
final class ReportRepository extends BaseRepository
{
    public function dashboard(): array
    {
        $counts = $this->fetchOne(
            "SELECT
               (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND role = 'client')   AS clients,
               (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND role = 'provider') AS providers,
               (SELECT COUNT(*) FROM bookings)                                             AS bookings,
               (SELECT COUNT(*) FROM bookings WHERE status = 'pending')                    AS bookings_pending,
               (SELECT COUNT(*) FROM disputes WHERE status = 'open')                       AS disputes_open,
               (SELECT COUNT(*) FROM withdrawals WHERE status = 'requested')               AS withdrawals_requested,
               (SELECT COUNT(*) FROM payouts WHERE status = 'pending')                     AS payouts_pending,
               (SELECT COALESCE(SUM(amount),0)            FROM payments WHERE status = 'completed') AS gross_volume,
               (SELECT COALESCE(SUM(commission_amount),0) FROM payments WHERE status = 'completed') AS total_commission"
        );
        return array_map(static fn ($v) => is_numeric($v) ? $v + 0 : $v, $counts ?? []);
    }

    public function revenueByMonth(int $months = 12): array
    {
        return $this->fetchAll(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month,
                    SUM(amount) AS gross, SUM(commission_amount) AS commission
               FROM payments
              WHERE status = 'completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
              GROUP BY month ORDER BY month",
            [$months]
        );
    }

    public function topCategories(int $limit = 10): array
    {
        return $this->fetchAll(
            'SELECT c.name, c.icon, COUNT(*) AS total_bookings, SUM(b.amount_total) AS volume
               FROM bookings b JOIN categories c ON c.id = b.category_id
              GROUP BY c.id ORDER BY total_bookings DESC LIMIT ' . $limit
        );
    }

    public function topProviders(int $limit = 10): array
    {
        return $this->fetchAll(
            'SELECT u.name, pp.rating_avg, pp.rating_count, pp.jobs_done
               FROM provider_profiles pp JOIN users u ON u.id = pp.user_id
              WHERE pp.rating_count > 0
              ORDER BY pp.rating_avg DESC, pp.jobs_done DESC LIMIT ' . $limit
        );
    }

    public function topClients(int $limit = 10): array
    {
        return $this->fetchAll(
            'SELECT u.name, COUNT(*) AS total_bookings, SUM(b.amount_total) AS total_spent
               FROM bookings b JOIN users u ON u.id = b.client_id
              GROUP BY u.id ORDER BY total_bookings DESC LIMIT ' . $limit
        );
    }

    public function activeUsersByMonth(int $months = 6): array
    {
        return $this->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(DISTINCT client_id) AS active_clients
               FROM bookings
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
              GROUP BY month ORDER BY month",
            [$months]
        );
    }

    /** Puntos geográficos de contrataciones para el mapa de calor. */
    public function bookingHeatmap(): array
    {
        return $this->fetchAll(
            'SELECT lat, lng, COUNT(*) AS weight FROM bookings
              WHERE lat IS NOT NULL AND lng IS NOT NULL
              GROUP BY lat, lng LIMIT 2000'
        );
    }
}
