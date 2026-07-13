<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Exceptions\HttpException;

/**
 * Subida segura de archivos: valida tipo real (MIME), tamaño,
 * regenera el nombre y los guarda fuera del webroot público del API.
 */
final class UploadService
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    /** @return string URL pública del archivo subido. */
    public function store(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException('Error al subir el archivo', 400);
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new HttpException('El archivo supera el máximo de 5 MB', 413);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new HttpException('Tipo de archivo no permitido (solo JPG, PNG, WEBP o PDF)', 415);
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        $dir = BASE_PATH . '/storage/uploads/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new HttpException('No se pudo guardar el archivo', 500);
        }

        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new HttpException('No se pudo guardar el archivo', 500);
        }

        return rtrim(Env::get('APP_URL', ''), '/') . '/uploads/' . date('Y/m') . '/' . $name;
    }
}
