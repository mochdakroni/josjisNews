<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('title', 255);
            $table->string('slug', 280)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content_body');
            $table->string('featured_image')->nullable();
            $table->string('caption_image')->nullable();
            $table->enum('status', ['draft', 'review', 'published'])
                ->default('draft');
            $table->boolean('is_headline')->default(false);
            $table->boolean('is_trending')->default(false);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Composite indexes untuk optimasi query portal berita high-traffic
            $table->index(['status', 'published_at'], 'idx_articles_status_published');
            $table->index(['status', 'is_headline', 'published_at'], 'idx_articles_headline');
            $table->index(['status', 'is_trending', 'published_at'], 'idx_articles_trending');
            $table->index(['category_id', 'status', 'published_at'], 'idx_articles_category_published');
            $table->fullText(['title', 'excerpt'], 'ft_articles_search');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
