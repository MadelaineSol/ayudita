<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\DTO\LoginDTO;
use App\DTO\RegisterDTO;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\PasswordResetService;
use App\Validation\Validator;

/**
 * Endpoints de autenticación: /api/v1/auth/*
 */
final class AuthController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function register(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'name'     => 'required|min:2|max:120',
            'email'    => 'required|email|max:190',
            'password' => 'required|min:8|max:100',
            'role'     => 'required|in:client,provider',
            'phone'    => 'phone',
        ]);

        $result = $this->auth->register(RegisterDTO::fromArray($data), $request->ip);
        Response::created($result);
    }

    public function login(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'email'    => 'required|email',
            'password' => 'required|min:1|max:100',
        ]);

        $result = $this->auth->login(
            LoginDTO::fromArray($data),
            $request->header('user-agent'),
            $request->ip
        );
        Response::json($result);
    }

    public function refresh(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'refresh_token' => 'required|min:20|max:200',
        ]);
        Response::json($this->auth->refresh(
            $data['refresh_token'],
            $request->header('user-agent'),
            $request->ip
        ));
    }

    public function logout(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'refresh_token' => 'required|min:20|max:200',
        ]);
        $this->auth->logout($data['refresh_token'], $request->userId(), $request->ip);
        Response::json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request): void
    {
        Response::json((new UserRepository())->publicProfile($request->user()));
    }

    public function forgotPassword(Request $request): void
    {
        $data = Validator::validate($request->body, ['email' => 'required|email']);
        $token = (new PasswordResetService())->requestReset($data['email']);
        // En producción, el token se envía por email. Nunca se expone en la respuesta.
        if ($token !== null) {
            \App\Core\Logger::info('Password reset token generado para ' . $data['email']);
        }
        Response::json(['message' => 'Si el email existe, vas a recibir instrucciones para recuperar tu contraseña']);
    }

    public function resetPassword(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'token'    => 'required|min:20|max:200',
            'password' => 'required|min:8|max:100',
        ]);
        (new PasswordResetService())->resetPassword($data['token'], $data['password'], $request->ip);
        Response::json(['message' => 'Contraseña actualizada. Iniciá sesión con tu nueva contraseña.']);
    }
}
