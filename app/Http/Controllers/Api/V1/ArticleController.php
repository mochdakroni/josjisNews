<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArticleIndexRequest;
use App\Http\Requests\Api\V1\TrendingArticleRequest;
use App\Http\Resources\Api\V1\ArticleDetailResource;
use App\Http\Resources\Api\V1\ArticleListResource;
use App\Models\Article;
use App\Services\ArticleViewTracker;
use App\Services\NewsCacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function __construct(
        protected NewsCacheService $cacheService,
        protected ArticleViewTracker $viewTracker,
    ) {}

    /**
     * GET /api/v1/articles
     * Mendukung pagination, filter kategori, search keyword, filter tag, dan sorting.
     */
    public function index(ArticleIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 12);
        $page = (int) ($validated['page'] ?? 1);
        $categorySlug = $validated['category'] ?? null;
        $tagSlug = $validated['tag'] ?? null;
        $search = $validated['search'] ?? null;
        $sort = $validated['sort'] ?? 'latest';

        $cacheKey = sprintf(
            'articles:index:p=%d:pp=%d:c=%s:t=%s:s=%s:sort=%s',
            $page,
            $perPage,
            $categorySlug ?? 'all',
            $tagSlug ?? 'all',
            $search ? md5(strtolower(trim($search))) : 'none',
            $sort
        );

        $payload = $this->cacheService->remember(
            tags: [NewsCacheService::TAG_ARTICLES],
            key: $cacheKey,
            ttlSeconds: 300, // 5 menit
            callback: function () use ($perPage, $categorySlug, $tagSlug, $search, $sort) {
                $query = Article::query()
                    ->select([
                        'id',
                        'user_id',
                        'category_id',
                        'title',
                        'slug',
                        'excerpt',
                        'featured_image',
                        'caption_image',
                        'status',
                        'is_headline',
                        'is_trending',
                        'views_count',
                        'published_at',
                    ])
                    ->published()
                    ->with([
                        'author:id,name,role',
                        'category:id,name,slug',
                        'tags:id,name,slug',
                    ]);

                if ($categorySlug) {
                    $query->whereHas('category', fn (Builder $q) => $q->where('slug', $categorySlug)->where('is_active', true));
                }

                if ($tagSlug) {
                    $query->whereHas('tags', fn (Builder $q) => $q->where('slug', $tagSlug));
                }

                if ($search) {
                    // Escape karakter wildcard SQL LIKE (% dan _) untuk mencegah LIKE Wildcard DoS
                    $escapedSearch = addcslashes(trim($search), '%_\\');
                    $keyword = '%' . $escapedSearch . '%';
                    $query->where(function (Builder $q) use ($keyword) {
                        $q->where('title', 'like', $keyword)
                            ->orWhere('excerpt', 'like', $keyword);
                    });
                }

                match ($sort) {
                    'popular' => $query->orderByDesc('views_count')->orderByDesc('published_at'),
                    'oldest' => $query->orderBy('published_at'),
                    default => $query->orderByDesc('published_at'),
                };

                $paginator = $query->paginate($perPage);

                return [
                    'items' => ArticleListResource::collection($paginator->getCollection())->resolve(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                ];
            }
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar artikel berhasil diambil.',
            'data' => $payload['items'],
            'meta' => $payload['meta'],
        ]);
    }

    /**
     * GET /api/v1/articles/headline
     * Mengambil 5 artikel utama bertanda headline.
     */
    public function headline(): JsonResponse
    {
        $headlines = $this->cacheService->remember(
            tags: [NewsCacheService::TAG_ARTICLES],
            key: 'articles:headline:top5',
            ttlSeconds: 600, // 10 menit
            callback: function () {
                $articles = Article::query()
                    ->select([
                        'id',
                        'user_id',
                        'category_id',
                        'title',
                        'slug',
                        'excerpt',
                        'featured_image',
                        'caption_image',
                        'status',
                        'is_headline',
                        'is_trending',
                        'views_count',
                        'published_at',
                    ])
                    ->headline()
                    ->with([
                        'author:id,name,role',
                        'category:id,name,slug',
                        'tags:id,name,slug',
                    ])
                    ->orderByDesc('published_at')
                    ->limit(5)
                    ->get();

                return ArticleListResource::collection($articles)->resolve();
            }
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar artikel headline berhasil diambil.',
            'data' => $headlines,
        ]);
    }

    /**
     * GET /api/v1/articles/trending
     * Mengambil berita terpopuler dalam 24-48 jam terakhir.
     */
    public function trending(TrendingArticleRequest $request): JsonResponse
    {
        $hours = (int) ($request->validated('hours') ?? 48);
        $limit = (int) ($request->validated('limit') ?? 10);

        $trendingArticles = $this->cacheService->remember(
            tags: [NewsCacheService::TAG_ARTICLES],
            key: "articles:trending:h={$hours}:l={$limit}",
            ttlSeconds: 300, // 5 menit
            callback: function () use ($hours, $limit) {
                $since = now()->subHours($hours);

                $articles = Article::query()
                    ->select([
                        'id',
                        'user_id',
                        'category_id',
                        'title',
                        'slug',
                        'excerpt',
                        'featured_image',
                        'caption_image',
                        'status',
                        'is_headline',
                        'is_trending',
                        'views_count',
                        'published_at',
                    ])
                    ->published()
                    ->with([
                        'author:id,name,role',
                        'category:id,name,slug',
                    ])
                    ->withCount([
                        'views as recent_views_count' => fn (Builder $q) => $q->where('viewed_at', '>=', $since),
                    ])
                    ->orderByDesc('is_trending')
                    ->orderByDesc('recent_views_count')
                    ->orderByDesc('views_count')
                    ->orderByDesc('published_at')
                    ->limit($limit)
                    ->get();

                return ArticleListResource::collection($articles)->resolve();
            }
        );

        return response()->json([
            'status' => 'success',
            'message' => "Daftar artikel trending ({$hours} jam terakhir) berhasil diambil.",
            'data' => $trendingArticles,
            'meta' => [
                'window_hours' => $hours,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * GET /api/v1/articles/{slug}
     * Detail artikel lengkap dengan relasi author, category, tags,
     * serta increment view counter yang di-throttle via Redis.
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $article = $this->cacheService->remember(
            tags: [NewsCacheService::TAG_ARTICLES, "news:article:{$slug}"],
            key: "articles:detail:{$slug}",
            ttlSeconds: 900, // 15 menit
            callback: function () use ($slug) {
                return Article::query()
                    ->published()
                    ->with([
                        'author:id,name,role',
                        'category:id,name,slug,description',
                        'tags:id,name,slug',
                    ])
                    ->where('slug', $slug)
                    ->first();
            }
        );

        if (! $article) {
            return response()->json([
                'status' => 'error',
                'message' => 'Artikel tidak ditemukan atau belum dipublikasikan.',
            ], 404);
        }

        // Throttle & rekam kunjungan menggunakan Redis SETNX
        $viewRecorded = $this->viewTracker->track($article, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail artikel berhasil diambil.',
            'data' => (new ArticleDetailResource($article))->resolve(),
            'meta' => [
                'view_recorded' => $viewRecorded,
            ],
        ]);
    }
}
