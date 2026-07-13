<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuestas HTTP en formato JSON unificado:
 * { "success": bool, "data": ..., "error": ..., "meta": ... }
 */
final class Response
{
    public static function json(mixed $data, int $status = 200, ?array $meta = null): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['success' => $status < 400, 'data' => $data];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status = 400, ?array $details = null): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['success' => false, 'error' => ['message' => $message]];
        if ($details !== null) {
            $payload['error']['details'] = $details;
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function created(mixed $data): never
    {
        self::json($data, 201);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }
}
