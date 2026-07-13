<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Paginator;
use App\Repositories\PaymentRepository;
use App\Services\PaymentService;
use App\Validation\Validator;

/**
 * Administración de pagos, liberaciones (payouts) y retiros.
 */
final class AdminPaymentController
{
    public function payments(Request $request): void
    {
        $paginator = new Paginator($request->queryParam('page'));
        Response::json((new PaymentRepository())->listAll($request->queryParam('status'), $paginator));
    }

    public function payouts(Request $request): void
    {
        $paginator = new Paginator($request->queryParam('page'));
        Response::json((new PaymentRepository())->listPayouts($request->queryParam('status'), $paginator));
    }

    public function approvePayout(Request $request): void
    {
        Response::json((new PaymentService())->approvePayout((int) $request->param('id'), $request->userId()));
    }

    public function withdrawals(Request $request): void
    {
        $paginator = new Paginator($request->queryParam('page'));
        Response::json((new PaymentRepository())->listWithdrawals($request->queryParam('status'), $paginator));
    }

    public function processWithdrawal(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'decision' => 'required|in:approved,rejected',
            'notes'    => 'max:255',
        ]);
        Response::json((new PaymentService())->processWithdrawal(
            (int) $request->param('id'),
            $data['decision'],
            $request->userId(),
            $data['notes'] ?? null
        ));
    }
}
