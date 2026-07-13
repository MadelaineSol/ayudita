<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Helpers\Text;
use App\Repositories\BannerRepository;
use App\Repositories\CategoryRepository;
use App\Validation\Validator;

/**
 * Administración de categorías de servicios y banners.
 */
final class AdminCatalogController
{
    // ---------- Categorías ----------

    public function categories(Request $request): void
    {
        Response::json((new CategoryRepository())->allForAdmin());
    }

    public function createCategory(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'name'        => 'required|min:2|max:80',
            'icon'        => 'max:16',
            'description' => 'max:255',
            'sort_order'  => 'integer',
        ]);
        $repo = new CategoryRepository();
        $id = $repo->create([
            'name'        => Text::clean($data['name']),
            'slug'        => Text::slug($data['name']),
            'icon'        => $data['icon'] ?? '💼',
            'description' => isset($data['description']) ? Text::clean($data['description']) : null,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ]);
        Response::created($repo->findById($id));
    }

    public function updateCategory(Request $request): void
    {
        $repo = new CategoryRepository();
        $category = $repo->findById((int) $request->param('id'));
        if ($category === null) {
            throw new HttpException('Categoría no encontrada', 404);
        }
        $data = Validator::validate($request->body, [
            'name'        => 'min:2|max:80',
            'icon'        => 'max:16',
            'description' => 'max:255',
            'active'      => 'boolean',
            'sort_order'  => 'integer',
        ]);
        if (isset($data['name'])) {
            $data['name'] = Text::clean($data['name']);
            $data['slug'] = Text::slug($data['name']);
        }
        if (isset($data['active'])) {
            $data['active'] = (int) (bool) $data['active'];
        }
        if ($data !== []) {
            $repo->updateById((int) $category['id'], $data);
        }
        Response::json($repo->findById((int) $category['id']));
    }

    public function deleteCategory(Request $request): void
    {
        (new CategoryRepository())->softDelete((int) $request->param('id'));
        Response::json(['message' => 'Categoría eliminada']);
    }

    // ---------- Banners ----------

    public function banners(Request $request): void
    {
        Response::json((new BannerRepository())->all());
    }

    public function createBanner(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'title'      => 'required|min:2|max:120',
            'image_url'  => 'max:500',
            'link'       => 'max:500',
            'emoji'      => 'max:16',
            'sort_order' => 'integer',
        ]);
        $data['title'] = Text::clean($data['title']);
        $id = (new BannerRepository())->create($data);
        Response::created(['id' => $id]);
    }

    public function updateBanner(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'title'      => 'min:2|max:120',
            'image_url'  => 'max:500',
            'link'       => 'max:500',
            'emoji'      => 'max:16',
            'active'     => 'boolean',
            'sort_order' => 'integer',
        ]);
        if (isset($data['active'])) {
            $data['active'] = (int) (bool) $data['active'];
        }
        if ($data !== []) {
            (new BannerRepository())->updateById((int) $request->param('id'), $data);
        }
        Response::json(['message' => 'Banner actualizado']);
    }

    public function deleteBanner(Request $request): void
    {
        (new BannerRepository())->delete((int) $request->param('id'));
        Response::json(['message' => 'Banner eliminado']);
    }
}
