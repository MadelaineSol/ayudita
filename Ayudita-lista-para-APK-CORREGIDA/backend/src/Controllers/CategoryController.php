<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\BannerRepository;
use App\Repositories\CategoryRepository;

/**
 * Catálogo público de categorías y banners.
 */
final class CategoryController
{
    public function index(Request $request): void
    {
        Response::json((new CategoryRepository())->allActive());
    }

    public function banners(Request $request): void
    {
        Response::json((new BannerRepository())->allActive());
    }
}
