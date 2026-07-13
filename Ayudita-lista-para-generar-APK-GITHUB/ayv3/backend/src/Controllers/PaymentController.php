<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Helpers\Paginator;
use App\Repositories\BookingRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProviderRepository;
use App\Services\PaymentService;
use App\Validation\Validator;

/**
 * Pagos del cliente, historial, comprobantes y retiros del prestador.
 */
final class PaymentController
{
    public function pay(Request $request): void
    {
        $booking = (new BookingRepository())->findById((int) $request->param('id'));
        if ($booking === null) {
            throw new HttpException('Trabajo no encontrado', 404);
        }
        $data = Validator::validate($request->body, [
            'method'       => 'required|in:card,transfer,mercadopago,wallet',
            'external_ref' => 'max:120',
        ]);

        $payment = (new PaymentService())->payBooking(
            $booking,
            $request->userId(),
            $data['method'],
            $data['external_ref'] ?? null
        );
        Response::created($payment);
    }

    public function index(Request $request): void
    {
        $paginator = new Paginator($request->queryParam('page'));
        Response::json((new PaymentRepository())->listForUser($request->userId(), $paginator));
    }

    /** Comprobante de pago en JSON estructurado (el frontend lo renderiza para descarga/impresión). */
    public function receipt(Request $request): void
    {
        $payment = (new PaymentRepository())->findById((int) $request->param('id'));
        if ($payment === null) {
            throw new HttpException('Pago no encontrado', 404);
        }
        $user = $request->user();
        if ((int) $payment['payer_id'] !== (int) $user['id'] && $user['role'] !== 'admin') {
            throw new HttpException('No tenés acceso a este comprobante', 403);
        }

        Response::json([
            'receipt_number' => 'AY-' . str_pad((string) $payment['id'], 8, '0', STR_PAD_LEFT),
            'booking_code'   => $payment['booking_code'],
            'payer'          => $payment['payer_name'],
            'amount'         => (float) $payment['amount'],
            'commission'     => (float) $payment['commission_amount'],
            'tax'            => (float) $payment['tax_amount'],
            'net_to_provider' => (float) $payment['net_amount'],
            'method'         => $payment['method'],
            'status'         => $payment['status'],
            'paid_at'        => $payment['paid_at'],
        ]);
    }

    public function requestWithdrawal(Request $request): void
    {
        $profile = (new ProviderRepository())->findByUserId($request->userId());
        if ($profile === null) {
            throw new HttpException('No tenés perfil de prestador', 404);
        }
        $data = Validator::validate($request->body, [
            'amount'    => 'required|numeric|min:1',
            'bank_info' => 'array',
        ]);

        $withdrawal = (new PaymentService())->requestWithdrawal(
            (int) $profile['id'],
            (float) $data['amount'],
            $data['bank_info'] ?? null
        );
        Response::created($withdrawal);
    }
}
