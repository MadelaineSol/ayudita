<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\DTO\LoginDTO;
use App\DTO\RegisterDTO;
use App\Exceptions\HttpException;
use App\Helpers\Text;
use App\Repositories\AuditRepository;
use App\Repositories\RefreshTokenRepository;
use App\Repositories\UserRepository;
use App\Security\Jwt;
use App\Security\Password;

/**
 * Autenticación: registro, login con bloqueo por intentos,
 * rotación de refresh tokens y recuperación de contraseña.
 */
final class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly RefreshTokenRepository $refreshTokens = new RefreshTokenRepository(),
        private readonly AuditRepository $audit = new AuditRepository(),
    ) {
    }

    public function register(RegisterDTO $dto, string $ip): array
    {
        if ($this->users->findByEmail($dto->email) !== null) {
            throw new HttpException('Ya existe una cuenta con ese email', 409);
        }

        $userId = $this->users->create([
            'role'          => $dto->role,
            'name'          => Text::clean($dto->name),
            'email'         => $dto->email,
            'phone'         => $dto->phone,
            'password_hash' => Password::hash($dto->password),
            'auth_provider' => $dto->authProvider,
            'status'        => 'active',
        ]);

        $this->audit->log($userId, 'user.register', 'users', $userId, $ip);
        $user = $this->users->findById($userId);
        return $this->issueTokens($user, null, $ip);
    }

    public function login(LoginDTO $dto, ?string $userAgent, string $ip): array
    {
        $user = $this->users->findByEmail($dto->email);

        if ($user === null || $user['password_hash'] === null) {
            throw new HttpException('Email o contraseña incorrectos', 401);
        }
        if ($user['status'] === 'blocked') {
            throw new HttpException('Tu cuenta está bloqueada. Contactá a soporte.', 403);
        }
        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            throw new HttpException('Cuenta bloqueada temporalmente por intentos fallidos. Probá más tarde.', 423);
        }

        if (!Password::verify($dto->password, $user['password_hash'])) {
            $this->users->registerFailedAttempt((int) $user['id'], self::MAX_FAILED_ATTEMPTS, self::LOCK_MINUTES);
            $this->audit->log((int) $user['id'], 'auth.login_failed', 'users', (int) $user['id'], $ip);
            throw new HttpException('Email o contraseña incorrectos', 401);
        }

        $this->users->resetFailedAttempts((int) $user['id']);
        $this->audit->log((int) $user['id'], 'auth.login', 'users', (int) $user['id'], $ip);
        return $this->issueTokens($user, $userAgent, $ip);
    }

    public function refresh(string $refreshToken, ?string $userAgent, string $ip): array
    {
        $stored = $this->refreshTokens->findValid($refreshToken);
        if ($stored === null) {
            throw new HttpException('Sesión expirada. Iniciá sesión de nuevo.', 401);
        }

        $user = $this->users->findById((int) $stored['user_id']);
        if ($user === null || $user['status'] === 'blocked') {
            throw new HttpException('Cuenta no disponible', 403);
        }

        // Rotación: el token usado queda revocado y se emite uno nuevo.
        $this->refreshTokens->revoke($refreshToken);
        return $this->issueTokens($user, $userAgent, $ip);
    }

    public function logout(string $refreshToken, int $userId, string $ip): void
    {
        $this->refreshTokens->revoke($refreshToken);
        $this->audit->log($userId, 'auth.logout', 'users', $userId, $ip);
    }

    public function issueTokens(array $user, ?string $userAgent, string $ip): array
    {
        $access = Jwt::issue([
            'sub'  => (int) $user['id'],
            'role' => $user['role'],
            'type' => 'access',
        ]);

        $refresh = bin2hex(random_bytes(40));
        $this->refreshTokens->store(
            (int) $user['id'],
            $refresh,
            Env::int('REFRESH_TTL_DAYS', 30),
            $userAgent,
            $ip
        );

        return [
            'user'          => $this->users->publicProfile($user),
            'access_token'  => $access,
            'refresh_token' => $refresh,
            'expires_in'    => Env::int('JWT_TTL', 900),
        ];
    }
}
