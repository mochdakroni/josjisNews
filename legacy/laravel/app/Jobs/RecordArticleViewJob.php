<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\ArticleView;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RecordArticleViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $articleId,
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
        public readonly string $viewedAt,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            ArticleView::create([
                'article_id' => $this->articleId,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'viewed_at' => $this->viewedAt,
            ]);

            Article::whereKey($this->articleId)->increment('views_count');
        });
    }
}
