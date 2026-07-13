<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Env;

/**
 * Implementación propia de JWT firmado con HMAC-SHA256 (HS256).
 * Sin dependencias externas.
 */
final class Jwt
{
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    /**
     * Genera un access token con expiración corta.
     *
     * @param array<string,mixed> $claims Claims adicionales (sub, role, ...)
     */
    public static function issue(array $claims, ?int $ttlSeconds = null): string
    {
        $ttl = $ttlSeconds ?? Env::int('JWT_TTL', 900);
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = array_merge($claims, [
            'iss' => Env::get('APP_URL', 'ayudita'),
            'iat' => time(),
            'exp' => time() + $ttl,
            'jti' => bin2hex(random_bytes(8)),
        ]);

        $segments = [
            self::base64UrlEncode(json_encode($header)),
            self::base64UrlEncode(json_encode($payload)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), self::secret(), true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Verifica firma y expiración. Devuelve los claims o null si es inválido.
     *
     * @return array<string,mixed>|null
     */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;
        $expected = hash_hmac('sha256', $header . '.' . $payload, self::secret(), true);
        if (!hash_equals($expected, self::base64UrlDecode($signature))) {
            return null;
        }
        $claims = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($claims) || ($claims['exp'] ?? 0) < time()) {
            return null;
        }
        return $claims;
    }

    private static function secret(): string
    {
        return Env::get('JWT_SECRET', 'change-me-please-in-production');
    }
}
