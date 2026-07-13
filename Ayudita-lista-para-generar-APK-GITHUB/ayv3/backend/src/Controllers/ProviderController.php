<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Helpers\Paginator;
use App\Repositories\ProviderRepository;
use App\Repositories\RatingRepository;

/**
 * Búsqueda pública de prestadores y detalle de perfil.
 */
final class ProviderController
{
    public function search(Request $request): void
    {
        $paginator = new Paginator($request->queryParam('page'), $request->queryParam('per_page', 20));
        $filters = array_intersect_key($request->query, array_flip([
            'category_id', 'q', 'min_price', 'max_price', 'min_rating',
            'min_experience', 'available', 'lat', 'lng', 'radius', 'sort',
        ]));

        $result = (new ProviderRepository())->search($filters, $paginator);
        Response::json($result['items'], 200, $paginator->meta($result['total']));
    }

    public function show(Request $request): void
    {
        $repo = new ProviderRepository();
        $provider = $repo->findById((int) $request->param('id'));
        if ($provider === null) {
            throw new HttpException('Prestador no encontrado', 404);
        }

        $provider['categories'] = $repo->categories($provider['id']);
        $provider['photos'] = $repo->photos($provider['id']);
        $provider['certificates'] = $repo->certificates($provider['id']);
        $provider['availability'] = $repo->availability($provider['id']);
        Response::json($provider);
    }

    public function ratings(Request $request): void
    {
        $repo = new ProviderRepository();
        $provider = $repo->findById((int) $request->param('id'));
        if ($provider === null) {
            throw new HttpException('Prestador no encontrado', 404);
        }
        Response::json((new RatingRepository())->listForUser((int) $provider['user_id']));
    }
}
