<?php
/**
 * Ayudita API - Front Controller
 * Punto de entrada único de la API REST (PHP 8.3, sin frameworks).
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/Core/Autoloader.php';

App\Core\Autoloader::register();
App\Core\Env::load(BASE_PATH . '/.env');

$app = new App\Core\App();
$app->run();
