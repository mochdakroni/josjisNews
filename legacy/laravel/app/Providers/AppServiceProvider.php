<?php

namespace App\Providers;

use App\Models\Article;
use App\Observers\ArticleObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Registrasi Observer untuk auto-invalidation Redis cache ketika artikel di-publish/di-update
        Article::observe(ArticleObserver::class);

        // Rate limiter untuk endpoint publik REST API (120 req/menit per IP)
        RateLimiter::for('api-public', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Rate limiter khusus upload gambar CKEditor (maks 20 upload/menit per user)
        RateLimiter::for('admin-upload', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }
}
