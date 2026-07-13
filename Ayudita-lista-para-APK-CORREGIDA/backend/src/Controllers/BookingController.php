<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\DTO\CreateBookingDTO;
use App\Exceptions\HttpException;
use App\Helpers\Paginator;
use App\Repositories\BookingRepository;
use App\Repositories\DisputeRepository;
use App\Repositories\RatingRepository;
use App\Services\BookingService;
use App\Validation\Validator;

/**
 * Contrataciones: creación, listado, ciclo de vida, extensión,
 * calificación y disputas.
 */
final class BookingController
{
    private BookingRepository $bookings;
    private BookingService $service;

    public function __construct()
    {
        $this->bookings = new BookingRepository();
        $this->service = new BookingService();
    }

    public function create(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'provider_id' => 'required|integer',
            'category_id' => 'required|integer',
            'unit'        => 'required|in:hour,day,week,month',
            'quantity'    => 'required|integer|min:1|max:365',
            'start_at'    => 'required|date',
            'description' => 'max:2000',
            'address'     => 'max:255',
            'lat'         => 'numeric',
            'lng'         => 'numeric',
        ]);

        Response::created($this->service->create($request->userId(), CreateBookingDTO::fromArray($data)));
    }

    public function index(Request $request): void
    {
        $paginator = new Paginator($request->queryParam('page'));
        $result = $this->bookings->listForUser(
            $request->userId(),
            (string) $request->user()['role'],
            $request->queryParam('status'),
            $paginator
        );
        Response::json($result['items'], 200, $paginator->meta($result['total']));
    }

    public function show(Request $request): void
    {
        $booking = $this->findOwned($request);
        $booking['history'] = $this->bookings->statusHistory((int) $booking['id']);
        $booking['extensions'] = $this->bookings->extensions((int) $booking['id']);
        Response::json($booking);
    }

    /** Transiciones del prestador: accept / reject / on_way / start / complete */
    public function transition(Request $request): void
    {
        $booking = $this->findOwned($request);
        $action = (string) $request->param('action');
        Response::json($this->service->transition($booking, $action, $request->user()));
    }

    public function cancel(Request $request): void
    {
        $booking = $this->findOwned($request);
        $data = Validator::validate($request->body, ['reason' => 'max:255']);
        Response::json($this->service->cancel($booking, $request->user(), $data['reason'] ?? null));
    }

    public function extend(Request $request): void
    {
        $booking = $this->findOwned($request);
        $data = Validator::validate($request->body, [
            'extra_quantity' => 'required|integer|min:1|max:365',
        ]);
        Response::json($this->service->extend($booking, $request->userId(), (int) $data['extra_quantity']));
    }

    public function rate(Request $request): void
    {
        $booking = $this->findOwned($request);
        if ($booking['status'] !== 'completed') {
            throw new HttpException('Solo se puede calificar un trabajo finalizado', 409);
        }

        $data = Validator::validate($request->body, [
            'stars'   => 'required|integer|min:1|max:5',
            'comment' => 'max:500',
        ]);

        $ratings = new RatingRepository();
        if ($ratings->existsForBooking((int) $booking['id'], $request->userId())) {
            throw new HttpException('Ya calificaste este trabajo', 409);
        }

        $isClient = (int) $booking['client_id'] === $request->userId();
        $ratedId = $isClient ? (int) $booking['provider_user_id'] : (int) $booking['client_id'];

        $ratings->create([
            'booking_id' => (int) $booking['id'],
            'rater_id'   => $request->userId(),
            'rated_id'   => $ratedId,
            'stars'      => (int) $data['stars'],
            'comment'    => isset($data['comment']) ? \App\Helpers\Text::clean((string) $data['comment']) : null,
        ]);
        Response::created(['message' => '¡Gracias por tu calificación! ⭐']);
    }

    public function dispute(Request $request): void
    {
        $booking = $this->findOwned($request);
        $data = Validator::validate($request->body, ['reason' => 'required|min:10|max:500']);

        (new DisputeRepository())->create([
            'booking_id' => (int) $booking['id'],
            'opened_by'  => $request->userId(),
            'reason'     => \App\Helpers\Text::clean($data['reason']),
        ]);
        $this->bookings->updateStatus((int) $booking['id'], 'disputed', $request->userId());
        Response::created(['message' => 'Disputa abierta. Un administrador la va a revisar.']);
    }

    /** Carga el booking y verifica que el usuario sea parte (o admin). */
    private function findOwned(Request $request): array
    {
        $booking = $this->bookings->findById((int) $request->param('id'));
        if ($booking === null) {
            throw new HttpException('Trabajo no encontrado', 404);
        }
        $user = $request->user();
        $isParty = (int) $booking['client_id'] === (int) $user['id']
                || (int) $booking['provider_user_id'] === (int) $user['id'];
        if (!$isParty && $user['role'] !== 'admin') {
            throw new HttpException('No tenés acceso a este trabajo', 403);
        }
        return $booking;
    }
}
