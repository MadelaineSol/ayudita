#!/usr/bin/env php
<?php
/**
 * Crea un usuario administrador de forma segura desde la terminal.
 * Uso: php bin/create-admin.php "Nombre" email@dominio.com
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/Core/Autoloader.php';
App\Core\Autoloader::register();
App\Core\Env::load(BASE_PATH . '/.env');

if ($argc < 3) {
    fwrite(STDERR, "Uso: php bin/create-admin.php \"Nombre\" email@dominio.com\n");
    exit(1);
}

[$_, $name, $email] = $argv;

fwrite(STDOUT, "Contraseña (mínimo 12 caracteres): ");
$password = trim((string) fgets(STDIN));
if (strlen($password) < 12) {
    fwrite(STDERR, "La contraseña debe tener al menos 12 caracteres.\n");
    exit(1);
}

$users = new App\Repositories\UserRepository();
if ($users->findByEmail(strtolower($email)) !== null) {
    fwrite(STDERR, "Ya existe un usuario con ese email.\n");
    exit(1);
}

$id = $users->create([
    'role'              => 'admin',
    'name'              => $name,
    'email'             => strtolower($email),
    'password_hash'     => App\Security\Password::hash($password),
    'status'            => 'active',
    'email_verified_at' => date('Y-m-d H:i:s'),
]);

fwrite(STDOUT, "Administrador creado con id $id\n");
