<?php

declare(strict_types=1);

use App\Controllers\Admin\AdminCatalogController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminPaymentController;
use App\Controllers\Admin\AdminSettingsController;
use App\Controllers\Admin\AdminUserController;
use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\CategoryController;
use App\Controllers\ChatController;
use App\Controllers\FavoriteController;
use App\Controllers\NotificationController;
use App\Controllers\PaymentController;
use App\Controllers\ProfileController;
use App\Controllers\ProviderController;
use App\Controllers\ProviderProfileController;
use App\Controllers\UploadController;
use App\Core\Response;
use App\Core\Router;

/**
 * Definición de rutas de la API v1.
 * Middlewares: 'auth' (JWT), 'role:x,y', 'throttle:max,segundos'.
 */
return static function (Router $router): void {

    $router->get('/api/v1/health', [CategoryController::class, 'index']); // catálogo como healthcheck con DB

    $router->group('/api/v1', [], static function (Router $router): void {

        // ---------- Público ----------
        $router->post('/auth/register',        [AuthController::class, 'register'],       ['throttle:5,300']);
        $router->post('/auth/login',           [AuthController::class, 'login'],          ['throttle:10,300']);
        $router->post('/auth/refresh',         [AuthController::class, 'refresh'],        ['throttle:30,300']);
        $router->post('/auth/forgot-password', [AuthController::class, 'forgotPassword'], ['throttle:5,600']);
        $router->post('/auth/reset-password',  [AuthController::class, 'resetPassword'],  ['throttle:5,600']);

        $router->get('/categories', [CategoryController::class, 'index']);
        $router->get('/banners',    [CategoryController::class, 'banners']);

        $router->get('/providers',              [ProviderController::class, 'search']);
        $router->get('/providers/{id}',         [ProviderController::class, 'show']);
        $router->get('/providers/{id}/ratings', [ProviderController::class, 'ratings']);

        // ---------- Autenticado ----------
        $router->group('', ['auth'], static function (Router $router): void {

            $router->get('/auth/me',      [AuthController::class, 'me']);
            $router->post('/auth/logout', [AuthController::class, 'logout']);
            $router->put('/profile',      [ProfileController::class, 'update']);
            $router->post('/uploads',     [UploadController::class, 'store'], ['throttle:30,300']);

            // Contrataciones
            $router->post('/bookings',                 [BookingController::class, 'create'], ['role:client']);
            $router->get('/bookings',                  [BookingController::class, 'index']);
            $router->get('/bookings/{id}',             [BookingController::class, 'show']);
            $router->post('/bookings/{id}/cancel',     [BookingController::class, 'cancel']);
            $router->post('/bookings/{id}/extend',     [BookingController::class, 'extend'], ['role:client']);
            $router->post('/bookings/{id}/rate',       [BookingController::class, 'rate']);
            $router->post('/bookings/{id}/dispute',    [BookingController::class, 'dispute']);
            $router->post('/bookings/{id}/pay',        [PaymentController::class, 'pay'], ['role:client', 'throttle:20,300']);
            // La transición genérica va última para no capturar las rutas específicas.
            $router->post('/bookings/{id}/{action}',   [BookingController::class, 'transition']);

            // Pagos
            $router->get('/payments',             [PaymentController::class, 'index']);
            $router->get('/payments/{id}/receipt', [PaymentController::class, 'receipt']);

            // Favoritos
            $router->get('/favorites',         [FavoriteController::class, 'index']);
            $router->post('/favorites',        [FavoriteController::class, 'store']);
            $router->delete('/favorites/{id}', [FavoriteController::class, 'destroy']);

            // Chat
            $router->get('/conversations',                [ChatController::class, 'conversations']);
            $router->post('/conversations',               [ChatController::class, 'open']);
            $router->get('/conversations/{id}/messages',  [ChatController::class, 'messages']);
            $router->post('/conversations/{id}/messages', [ChatController::class, 'send'], ['throttle:60,60']);

            // Notificaciones
            $router->get('/notifications',       [NotificationController::class, 'index']);
            $router->post('/notifications/read', [NotificationController::class, 'markRead']);

            // Perfil de prestador (rol: provider)
            $router->group('/provider', ['role:provider'], static function (Router $router): void {
                $router->get('/profile',        [ProviderProfileController::class, 'show']);
                $router->put('/profile',        [ProviderProfileController::class, 'update']);
                $router->post('/photos',        [ProviderProfileController::class, 'addPhoto']);
                $router->post('/certificates',  [ProviderProfileController::class, 'addCertificate']);
                $router->put('/availability',   [ProviderProfileController::class, 'setAvailability']);
                $router->get('/earnings',       [ProviderProfileController::class, 'earnings']);
                $router->post('/withdrawals',   [PaymentController::class, 'requestWithdrawal']);
            });

            // ---------- Panel Administrador (rol: admin) ----------
            $router->group('/admin', ['role:admin'], static function (Router $router): void {
                $router->get('/dashboard',       [AdminDashboardController::class, 'index']);
                $router->get('/reports/{type}',  [AdminDashboardController::class, 'report']);

                $router->get('/users',           [AdminUserController::class, 'index']);
                $router->put('/users/{id}',      [AdminUserController::class, 'update']);
                $router->delete('/users/{id}',   [AdminUserController::class, 'destroy']);

                $router->get('/payments',                    [AdminPaymentController::class, 'payments']);
                $router->get('/payouts',                     [AdminPaymentController::class, 'payouts']);
                $router->post('/payouts/{id}/approve',       [AdminPaymentController::class, 'approvePayout']);
                $router->get('/withdrawals',                 [AdminPaymentController::class, 'withdrawals']);
                $router->post('/withdrawals/{id}/process',   [AdminPaymentController::class, 'processWithdrawal']);

                $router->get('/categories',           [AdminCatalogController::class, 'categories']);
                $router->post('/categories',          [AdminCatalogController::class, 'createCategory']);
                $router->put('/categories/{id}',      [AdminCatalogController::class, 'updateCategory']);
                $router->delete('/categories/{id}',   [AdminCatalogController::class, 'deleteCategory']);

                $router->get('/banners',         [AdminCatalogController::class, 'banners']);
                $router->post('/banners',        [AdminCatalogController::class, 'createBanner']);
                $router->put('/banners/{id}',    [AdminCatalogController::class, 'updateBanner']);
                $router->delete('/banners/{id}', [AdminCatalogController::class, 'deleteBanner']);

                $router->get('/settings',        [AdminSettingsController::class, 'settings']);
                $router->put('/settings',        [AdminSettingsController::class, 'updateSettings']);
                $router->get('/disputes',        [AdminSettingsController::class, 'disputes']);
                $router->post('/disputes/{id}/resolve', [AdminSettingsController::class, 'resolveDispute']);
                $router->get('/logs',            [AdminSettingsController::class, 'logs']);
            });
        });
    });
};
