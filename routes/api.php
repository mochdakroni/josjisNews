<?php

use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['throttle:api-public'])
    ->group(function () {
        // Articles Endpoints
        Route::get('/articles', [ArticleController::class, 'index'])
            ->name('api.v1.articles.index');

        Route::get('/articles/headline', [ArticleController::class, 'headline'])
            ->name('api.v1.articles.headline');

        Route::get('/articles/trending', [ArticleController::class, 'trending'])
            ->name('api.v1.articles.trending');

        Route::get('/articles/{slug}', [ArticleController::class, 'show'])
            ->where('slug', '[a-z0-9-]+')
            ->name('api.v1.articles.show');

        // Categories Endpoint
        Route::get('/categories', [CategoryController::class, 'index'])
            ->name('api.v1.categories.index');

        // Popular Tags Endpoint
        Route::get('/tags/popular', [TagController::class, 'popular'])
            ->name('api.v1.tags.popular');
    });
