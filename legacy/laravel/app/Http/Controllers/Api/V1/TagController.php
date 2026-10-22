<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TagResource;
use App\Models\Tag;
use App\Services\NewsCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(
        protected NewsCacheService $cacheService
    ) {}

    /**
     * GET /api/v1/tags/popular
     * Daftar tag yang paling sering digunakan pada artikel yang sudah published.
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 15), 1), 50);

        $tags = $this->cacheService->remember(
            tags: [NewsCacheService::TAG_TAGS],
            key: "tags:popular:limit={$limit}",
            ttlSeconds: 900, // 15 menit
            callback: function () use ($limit) {
                $items = Tag::query()
                    ->withCount([
                        'articles' => fn ($query) => $query->published(),
                    ])
                    ->having('articles_count', '>', 0)
                    ->orderByDesc('articles_count')
                    ->orderBy('name')
                    ->limit($limit)
                    ->get();

                return TagResource::collection($items)->resolve();
            }
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tag populer berhasil diambil.',
            'data' => $tags,
        ]);
    }
}
