<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Helpers\Paginator;
use App\Helpers\Text;
use App\Repositories\PaymentRepository;
use App\Repositories\ProviderRepository;
use App\Services\UploadService;
use App\Validation\Validator;

/**
 * Gestión del propio perfil de prestador (rol: provider).
 */
final class ProviderProfileController
{
    private ProviderRepository $providers;

    public function __construct()
    {
        $this->providers = new ProviderRepository();
    }

    public function show(Request $request): void
    {
        $profile = $this->requireProfile($request->userId(), createIfMissing: true);
        $profile['categories'] = $this->providers->categories((int) $profile['id']);
        $profile['photos'] = $this->providers->photos((int) $profile['id']);
        $profile['certificates'] = $this->providers->certificates((int) $profile['id']);
        $profile['availability'] = $this->providers->availability((int) $profile['id']);
        Response::json($profile);
    }

    public function update(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'bio'              => 'max:2000',
            'experience_years' => 'integer|max:80',
            'rate_hour'        => 'numeric|min:0',
            'rate_day'         => 'numeric|min:0',
            'radius_km'        => 'integer|min:1|max:500',
            'available'        => 'boolean',
            'category_ids'     => 'array',
        ]);

        $profile = $this->requireProfile($request->userId(), createIfMissing: true);
        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);

        if (isset($data['bio'])) {
            $data['bio'] = Text::clean((string) $data['bio']);
        }
        if (isset($data['available'])) {
            $data['available'] = (int) (bool) $data['available'];
        }
        if ($data !== []) {
            $this->providers->updateByUserId($request->userId(), $data);
        }
        if (is_array($categoryIds)) {
            $this->providers->setCategories((int) $profile['id'], $categoryIds);
        }

        $this->show($request);
    }

    public function addPhoto(Request $request): void
    {
        $profile = $this->requireProfile($request->userId());
        if (!isset($request->files['photo'])) {
            throw new HttpException('Falta el archivo "photo"', 422);
        }
        $url = (new UploadService())->store($request->files['photo']);
        $this->providers->addPhoto((int) $profile['id'], $url);
        Response::created(['url' => $url]);
    }

    public function addCertificate(Request $request): void
    {
        $profile = $this->requireProfile($request->userId());
        $data = Validator::validate($request->body, ['title' => 'required|min:3|max:150']);

        $url = null;
        if (isset($request->files['file'])) {
            $url = (new UploadService())->store($request->files['file']);
        }
        $this->providers->addCertificate((int) $profile['id'], Text::clean($data['title']), $url);
        Response::created(['title' => $data['title'], 'url' => $url]);
    }

    public function setAvailability(Request $request): void
    {
        $profile = $this->requireProfile($request->userId());
        $slots = $request->input('slots');
        if (!is_array($slots)) {
            throw new HttpException('Formato inválido: se espera "slots" como lista', 422);
        }
        foreach ($slots as $slot) {
            Validator::validate(is_array($slot) ? $slot : [], [
                'weekday'   => 'required|integer|min:0|max:6',
                'from_time' => 'required|max:8',
                'to_time'   => 'required|max:8',
            ]);
        }
        $this->providers->setAvailability((int) $profile['id'], $slots);
        Response::json(['message' => 'Disponibilidad actualizada']);
    }

    public function earnings(Request $request): void
    {
        $profile = $this->requireProfile($request->userId());
        $payments = new PaymentRepository();
        Response::json(array_merge(
            $payments->providerEarnings((int) $profile['id']),
            ['withdrawals' => $payments->listWithdrawalsForProvider((int) $profile['id'])]
        ));
    }

    private function requireProfile(int $userId, bool $createIfMissing = false): array
    {
        $profile = $this->providers->findByUserId($userId);
        if ($profile === null) {
            if (!$createIfMissing) {
                throw new HttpException('Todavía no creaste tu perfil de prestador', 404);
            }
            $this->providers->create($userId, []);
            $profile = $this->providers->findByUserId($userId);
        }
        return $profile;
    }
}
