<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Paginator;

/**
 * Pagos, liberaciones (payouts) y retiros.
 */
final class PaymentRepository extends BaseRepository
{
    public function create(array $data): int
    {
        return $this->insert('payments', $data);
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT p.*, b.code AS booking_code, b.provider_id, u.name AS payer_name
               FROM payments p
               JOIN bookings b ON b.id = p.booking_id
               JOIN users u    ON u.id = p.payer_id
              WHERE p.id = ?',
            [$id]
        );
    }

    public function listForUser(int $userId, Paginator $paginator): array
    {
        return $this->fetchAll(
            'SELECT p.*, b.code AS booking_code
               FROM payments p
               JOIN bookings b ON b.id = p.booking_id
              WHERE p.payer_id = ?
              ORDER BY p.created_at DESC
              LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset,
            [$userId]
        );
    }

    public function listAll(?string $status, Paginator $paginator): array
    {
        $where = '';
        $params = [];
        if ($status !== null && $status !== '') {
            $where = 'WHERE p.status = ?';
            $params[] = $status;
        }
        return $this->fetchAll(
            "SELECT p.*, b.code AS booking_code, u.name AS payer_name
               FROM payments p
               JOIN bookings b ON b.id = p.booking_id
               JOIN users u    ON u.id = p.payer_id
              $where
              ORDER BY p.created_at DESC
              LIMIT {$paginator->perPage} OFFSET {$paginator->offset}",
            $params
        );
    }

    public function updateById(int $id, array $data): void
    {
        $this->update('payments', $data, 'id = :_id', ['_id' => $id]);
    }

    // ------ Payouts (liberación de fondos al prestador) ------

    public function createPayout(array $data): int
    {
        return $this->insert('payouts', $data);
    }

    public function findPayout(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM payouts WHERE id = ?', [$id]);
    }

    public function listPayouts(?string $status, Paginator $paginator): array
    {
        $where = '';
        $params = [];
        if ($status !== null && $status !== '') {
            $where = 'WHERE po.status = ?';
            $params[] = $status;
        }
        return $this->fetchAll(
            "SELECT po.*, u.name AS provider_name, p.booking_id
               FROM payouts po
               JOIN provider_profiles pp ON pp.id = po.provider_id
               JOIN users u ON u.id = pp.user_id
               JOIN payments p ON p.id = po.payment_id
              $where
              ORDER BY po.created_at DESC
              LIMIT {$paginator->perPage} OFFSET {$paginator->offset}",
            $params
        );
    }

    public function updatePayout(int $id, array $data): void
    {
        $this->update('payouts', $data, 'id = :_id', ['_id' => $id]);
    }

    // ------ Withdrawals (retiros del prestador) ------

    public function createWithdrawal(array $data): int
    {
        return $this->insert('withdrawals', $data);
    }

    public function findWithdrawal(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM withdrawals WHERE id = ?', [$id]);
    }

    public function listWithdrawalsForProvider(int $providerId): array
    {
        return $this->fetchAll(
            'SELECT id, amount, status, notes, created_at, processed_at
               FROM withdrawals WHERE provider_id = ? ORDER BY created_at DESC LIMIT 100',
            [$providerId]
        );
    }

    public function listWithdrawals(?string $status, Paginator $paginator): array
    {
        $where = '';
        $params = [];
        if ($status !== null && $status !== '') {
            $where = 'WHERE w.status = ?';
            $params[] = $status;
        }
        return $this->fetchAll(
            "SELECT w.*, u.name AS provider_name, pp.balance AS provider_balance
               FROM withdrawals w
               JOIN provider_profiles pp ON pp.id = w.provider_id
               JOIN users u ON u.id = pp.user_id
              $where
              ORDER BY w.created_at DESC
              LIMIT {$paginator->perPage} OFFSET {$paginator->offset}",
            $params
        );
    }

    public function updateWithdrawal(int $id, array $data): void
    {
        $this->update('withdrawals', $data, 'id = :_id', ['_id' => $id]);
    }

    /** Ingresos del prestador: total ganado, pendiente y disponible. */
    public function providerEarnings(int $providerId): array
    {
        $released = $this->fetchOne(
            "SELECT COALESCE(SUM(amount),0) AS total FROM payouts
              WHERE provider_id = ? AND status = 'approved'",
            [$providerId]
        );
        $pending = $this->fetchOne(
            "SELECT COALESCE(SUM(amount),0) AS total FROM payouts
              WHERE provider_id = ? AND status = 'pending'",
            [$providerId]
        );
        $balance = $this->fetchOne(
            'SELECT balance FROM provider_profiles WHERE id = ?',
            [$providerId]
        );
        return [
            'total_released' => (float) $released['total'],
            'pending_release' => (float) $pending['total'],
            'balance' => (float) ($balance['balance'] ?? 0),
        ];
    }
}
