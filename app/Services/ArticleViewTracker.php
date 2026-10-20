<?php

namespace App\Services;

use App\Jobs\RecordArticleViewJob;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArticleViewTracker
{
    /**
     * Durasi throttle per IP/fingerprint untuk 1 artikel (dalam detik).
     * Default: 6 jam (21600 detik) agar refresh halaman berulang tidak memanipulasi view count.
     */
    private const THROTTLE_TTL_SECONDS = 21600;

    /**
     * Catat view artikel dengan Redis atomic lock (`Cache::add` / Redis `SETNX`).
     */
    public function track(Article $article, Request $request): bool
    {
        $ipAddress = (string) ($request->ip() ?? '0.0.0.0');
        $userAgent = substr((string) $request->userAgent(), 0, 500);

        // Hash fingerprint gabungan IP + User-Agent
        $visitorHash = hash('sha256', $ipAddress . '|' . $userAgent);
        $redisThrottleKey = "news:view_lock:{$article->id}:{$visitorHash}";

        // Cache::add bersifat atomic di Redis (SET key value NX EX ttl).
        // Mengembalikan true hanya jika key belum ada di Redis.
        $isFirstViewInWindow = Cache::add(
            $redisThrottleKey,
            now()->timestamp,
            self::THROTTLE_TTL_SECONDS
        );

        if (! $isFirstViewInWindow) {
            return false;
        }

        // Dispatch ke queue/setelah response dikirim agar response API tetap cepat (<15ms)
        RecordArticleViewJob::dispatchAfterResponse(
            articleId: $article->id,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            viewedAt: now()->toDateTimeString(),
        );

        return true;
    }
}
