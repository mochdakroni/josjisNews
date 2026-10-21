<?php

namespace App\Services;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class NewsCacheService
{
    public const TAG_ARTICLES = 'news:articles';
    public const TAG_CATEGORIES = 'news:categories';
    public const TAG_TAGS = 'news:tags';

    public const VERSION_KEY = 'news:cache_version';

    /**
     * Simpan/ambil data dari cache Redis dengan dukungan Cache Tags
     * (dan fallback version key jika driver lokal tidak mendukung tags).
     */
    public function remember(array $tags, string $key, int $ttlSeconds, Closure $callback): mixed
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            return Cache::tags($tags)->remember($key, $ttlSeconds, $callback);
        }

        $version = (int) Cache::get(self::VERSION_KEY, 1);
        $versionedKey = "v{$version}:{$key}";

        return Cache::remember($versionedKey, $ttlSeconds, $callback);
    }

    /**
     * Invalidate cache artikel, kategori, dan tag populer ketika artikel dipublish/diupdate/dihapus.
     */
    public function flushArticleCaches(?string $slug = null, ?string $oldSlug = null): void
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            Cache::tags([
                self::TAG_ARTICLES,
                self::TAG_CATEGORIES,
                self::TAG_TAGS,
            ])->flush();

            if ($slug) {
                Cache::tags(["news:article:{$slug}"])->flush();
            }

            if ($oldSlug && $oldSlug !== $slug) {
                Cache::tags(["news:article:{$oldSlug}"])->flush();
            }

            return;
        }

        // Fallback atomic increment version jika driver non-taggable
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 2);
        } else {
            Cache::increment(self::VERSION_KEY);
        }
    }
}
