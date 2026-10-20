<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Services\NewsCacheService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        protected NewsCacheService $cacheService
    ) {}

    /**
     * GET /api/v1/categories
     * Daftar kategori aktif beserta jumlah artikel yang telah terbit.
     */
    public function index(): JsonResponse
    {
        $categories = $this->cacheService->remember(
            tags: [NewsCacheService::TAG_CATEGORIES],
            key: 'categories:active:list',
            ttlSeconds: 1800, // 30 menit
            callback: function () {
                $items = Category::query()
                    ->active()
                    ->withCount([
                        'articles' => fn ($query) => $query->published(),
                    ])
                    ->orderBy('name')
                    ->get();

                return CategoryResource::collection($items)->resolve();
            }
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kategori aktif berhasil diambil.',
            'data' => $categories,
        ]);
    }
}
