<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\FavoriteRepository;
use App\Validation\Validator;

/**
 * Prestadores favoritos del usuario.
 */
final class FavoriteController
{
    public function index(Request $request): void
    {
        Response::json((new FavoriteRepository())->listForUser($request->userId()));
    }

    public function store(Request $request): void
    {
        $data = Validator::validate($request->body, ['provider_id' => 'required|integer']);
        (new FavoriteRepository())->add($request->userId(), (int) $data['provider_id']);
        Response::created(['message' => 'Agregado a favoritos 💛']);
    }

    public function destroy(Request $request): void
    {
        (new FavoriteRepository())->remove($request->userId(), (int) $request->param('id'));
        Response::json(['message' => 'Quitado de favoritos']);
    }
}
