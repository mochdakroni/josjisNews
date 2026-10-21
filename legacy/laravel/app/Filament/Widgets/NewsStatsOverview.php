<?php

namespace App\Filament\Widgets;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NewsStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $publishedCount = Article::where('status', ArticleStatus::PUBLISHED)->count();
        $reviewCount = Article::where('status', ArticleStatus::REVIEW)->count();
        $viewsLast24h = ArticleView::where('viewed_at', '>=', now()->subHours(24))->count();
        $activeCategories = Category::where('is_active', true)->count();
        $totalJournalists = User::count();

        return [
            Stat::make('Artikel Terbit (Published)', number_format($publishedCount))
                ->description("{$reviewCount} artikel menunggu review editor")
                ->descriptionIcon('heroicon-m-newspaper')
                ->color('success'),

            Stat::make('Pembaca 24 Jam Terakhir', number_format($viewsLast24h))
                ->description('Redis Throttled Unique Views')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),

            Stat::make('Kategori Aktif', number_format($activeCategories))
                ->description('Kanal navigasi publik')
                ->descriptionIcon('heroicon-m-folder')
                ->color('info'),

            Stat::make('Tim Redaksi & User', number_format($totalJournalists))
                ->description('Super Admin, Editor & Jurnalis')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }
}
