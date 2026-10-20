<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('viewed_at')->useCurrent();

            // Index untuk agregasi berita trending 24-48 jam terakhir
            $table->index(['viewed_at', 'article_id'], 'idx_article_views_window');
            $table->index(['article_id', 'viewed_at'], 'idx_article_views_article_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_views');
    }
};
