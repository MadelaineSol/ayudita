<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Services\UploadService;

/**
 * Subida genérica de archivos (avatar, imágenes de chat, etc.).
 */
final class UploadController
{
    public function store(Request $request): void
    {
        if (!isset($request->files['file'])) {
            throw new HttpException('Falta el archivo "file"', 422);
        }
        Response::created(['url' => (new UploadService())->store($request->files['file'])]);
    }
}
