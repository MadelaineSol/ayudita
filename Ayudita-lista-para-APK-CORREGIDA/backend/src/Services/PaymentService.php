<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\HttpException;
use App\Repositories\AuditRepository;
use App\Repositories\BookingRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProviderRepository;
use App\Repositories\SettingRepository;

/**
 * Pagos: el cliente paga a la plataforma, la plataforma retiene la comisión
 * configurada por el admin, y el admin libera (payout) el neto al prestador.
 */
final class PaymentService
{
    public function __construct(
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly BookingRepository $bookings = new BookingRepository(),
        private readonly ProviderRepository $providers = new ProviderRepository(),
        private readonly SettingRepository $settings = new SettingRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly AuditRepository $audit = new AuditRepository(),
    ) {
    }

    /**
     * Registra el pago del cliente hacia la plataforma.
     * En producción, aquí se integra la pasarela (Mercado Pago / Stripe):
     * el registro queda 'pending' hasta la confirmación del webhook.
     */
    public function payBooking(array $booking, int $payerId, string $method, ?string $externalRef): array
    {
        if ((int) $booking['client_id'] !== $payerId) {
            throw new HttpException('Solo el cliente puede pagar este trabajo', 403);
        }
        if ($booking['payment_status'] === 'paid' || $booking['payment_status'] === 'released') {
            throw new HttpException('Este trabajo ya está pagado', 409);
        }
        if (in_array($booking['status'], ['cancelled', 'disputed'], true)) {
            throw new HttpException('No se puede pagar un trabajo cancelado o en disputa', 409);
        }

        $commissionPercent = $this->settings->float('commission_percent', 10);
        $taxPercent = $this->settings->float('tax_percent', 0);
        $amount = (float) $booking['amount_total'];
        $commission = round($amount * $commissionPercent / 100, 2);
        $tax = round($amount * $taxPercent / 100, 2);
        $net = round($amount - $commission - $tax, 2);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $paymentId = $this->payments->create([
                'booking_id'         => (int) $booking['id'],
                'payer_id'           => $payerId,
                'amount'             => $amount,
                'commission_percent' => $commissionPercent,
                'commission_amount'  => $commission,
                'tax_percent'        => $taxPercent,
                'tax_amount'         => $tax,
                'net_amount'         => $net,
                'method'             => $method,
                'status'             => 'completed', // simulado; con pasarela real: 'pending'
                'external_ref'       => $externalRef,
                'paid_at'            => date('Y-m-d H:i:s'),
            ]);

            $this->bookings->updateById((int) $booking['id'], ['payment_status' => 'paid']);

            // Payout pendiente de aprobación por el administrador.
            $this->payments->createPayout([
                'provider_id' => (int) $booking['provider_id'],
                'payment_id'  => $paymentId,
                'amount'      => $net,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->notifications->notify(
            (int) $booking['provider_user_id'],
            'payment.received',
            '¡El cliente pagó el trabajo! 💰',
            'El pago del trabajo ' . $booking['code'] . ' fue recibido por la plataforma. El administrador liberará tu dinero pronto.',
            ['booking_id' => (int) $booking['id']]
        );
        $this->audit->log($payerId, 'payment.create', 'payments', $paymentId);

        return $this->payments->findById($paymentId);
    }

    /** El administrador aprueba la liberación del neto al saldo del prestador. */
    public function approvePayout(int $payoutId, int $adminId): array
    {
        $payout = $this->payments->findPayout($payoutId);
        if ($payout === null) {
            throw new HttpException('Liberación no encontrada', 404);
        }
        if ($payout['status'] !== 'pending') {
            throw new HttpException('La liberación ya fue procesada', 409);
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $this->payments->updatePayout($payoutId, [
                'status'      => 'approved',
                'approved_by' => $adminId,
                'approved_at' => date('Y-m-d H:i:s'),
            ]);
            $this->providers->adjustBalance((int) $payout['provider_id'], (float) $payout['amount']);

            $payment = $this->payments->findById((int) $payout['payment_id']);
            $this->bookings->updateById((int) $payment['booking_id'], ['payment_status' => 'released']);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->audit->log($adminId, 'payout.approve', 'payouts', $payoutId);
        return $this->payments->findPayout($payoutId);
    }

    /** El prestador solicita retirar su saldo disponible. */
    public function requestWithdrawal(int $providerId, float $amount, ?array $bankInfo): array
    {
        $profile = \App\Core\Database::connection();
        $stmt = $profile->prepare('SELECT balance FROM provider_profiles WHERE id = ?');
        $stmt->execute([$providerId]);
        $balance = (float) ($stmt->fetch()['balance'] ?? 0);

        $min = $this->settings->float('min_withdrawal', 0);
        if ($amount < $min) {
            throw new HttpException("El monto mínimo de retiro es $min", 422);
        }
        if ($amount > $balance) {
            throw new HttpException('No tenés saldo suficiente', 422);
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $withdrawalId = $this->payments->createWithdrawal([
                'provider_id' => $providerId,
                'amount'      => $amount,
                'bank_info'   => $bankInfo !== null ? json_encode($bankInfo, JSON_UNESCAPED_UNICODE) : null,
            ]);
            // El saldo se reserva inmediatamente para evitar dobles retiros.
            $this->providers->adjustBalance($providerId, -$amount);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return $this->payments->findWithdrawal($withdrawalId);
    }

    /** El administrador procesa un retiro (aprobado => pagado, o rechazado => devuelve saldo). */
    public function processWithdrawal(int $withdrawalId, string $decision, int $adminId, ?string $notes): array
    {
        $withdrawal = $this->payments->findWithdrawal($withdrawalId);
        if ($withdrawal === null) {
            throw new HttpException('Retiro no encontrado', 404);
        }
        if ($withdrawal['status'] !== 'requested') {
            throw new HttpException('El retiro ya fue procesado', 409);
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new HttpException('Decisión inválida', 400);
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $this->payments->updateWithdrawal($withdrawalId, [
                'status'       => $decision === 'approved' ? 'paid' : 'rejected',
                'processed_by' => $adminId,
                'processed_at' => date('Y-m-d H:i:s'),
                'notes'        => $notes,
            ]);
            if ($decision === 'rejected') {
                $this->providers->adjustBalance((int) $withdrawal['provider_id'], (float) $withdrawal['amount']);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->audit->log($adminId, 'withdrawal.' . $decision, 'withdrawals', $withdrawalId);
        return $this->payments->findWithdrawal($withdrawalId);
    }
}
