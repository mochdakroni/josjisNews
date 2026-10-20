<?php

namespace App\Observers;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Services\HtmlSanitizerService;
use App\Services\NewsCacheService;
use Illuminate\Support\Facades\Auth;

class ArticleObserver
{
    public function __construct(
        protected NewsCacheService $cacheService,
        protected HtmlSanitizerService $htmlSanitizer,
    ) {}

    /**
     * 1. Sanitasi konten HTML CKEditor dari celah Stored XSS sebelum disimpan ke database.
     * 2. Proteksi eskalasi hak akses: Jurnalis tidak boleh mem-publish atau mengubah flag headline/trending.
     * 3. Otomatis set published_at jika status berubah menjadi PUBLISHED.
     */
    public function saving(Article $article): void
    {
        if ($article->isDirty('content_body')) {
            $article->content_body = $this->htmlSanitizer->sanitize($article->content_body);
        }

        if ($article->isDirty('excerpt') && $article->excerpt) {
            $article->excerpt = strip_tags($article->excerpt);
        }

        $user = Auth::user();
        if ($user && ! ($user->role?->canPublish() ?? false)) {
            if ($article->status === ArticleStatus::PUBLISHED) {
                $article->status = ArticleStatus::REVIEW;
            }
            $article->is_headline = false;
            $article->is_trending = false;
        }

        if ($article->status === ArticleStatus::PUBLISHED && is_null($article->published_at)) {
            $article->published_at = now();
        }
    }

    /**
     * Invalidate cache Redis otomatis ketika artikel baru dipublish atau artikel yang sudah/pernah publish diupdate.
     */
    public function saved(Article $article): void
    {
        $wasPublished = $article->getOriginal('status') === ArticleStatus::PUBLISHED
            || $article->getOriginal('status') === ArticleStatus::PUBLISHED->value;
        $isPublished = $article->status === ArticleStatus::PUBLISHED;

        if ($isPublished || $wasPublished) {
            $this->cacheService->flushArticleCaches(
                slug: $article->slug,
                oldSlug: $article->getOriginal('slug')
            );
        }
    }

    /**
     * Invalidate cache Redis ketika artikel dihapus.
     */
    public function deleted(Article $article): void
    {
        if ($article->status === ArticleStatus::PUBLISHED) {
            $this->cacheService->flushArticleCaches(slug: $article->slug);
        }
    }
}
