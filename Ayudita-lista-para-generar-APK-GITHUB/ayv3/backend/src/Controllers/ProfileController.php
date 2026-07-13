<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Text;
use App\Repositories\UserRepository;
use App\Validation\Validator;

/**
 * Perfil propio del usuario autenticado.
 */
final class ProfileController
{
    public function update(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'name'    => 'min:2|max:120',
            'phone'   => 'phone',
            'address' => 'max:255',
            'city'    => 'max:120',
            'lat'     => 'numeric',
            'lng'     => 'numeric',
            'avatar_url' => 'max:500',
        ]);

        foreach (['name', 'address', 'city'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = Text::clean((string) $data[$field]);
            }
        }

        $repo = new UserRepository();
        if ($data !== []) {
            $repo->updateById($request->userId(), $data);
        }
        Response::json($repo->publicProfile($repo->findById($request->userId())));
    }
}
