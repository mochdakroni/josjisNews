<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Observers\ArticleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([ArticleObserver::class])]
class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content_body',
        'featured_image',
        'caption_image',
        'status',
        'is_headline',
        'is_trending',
        'views_count',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'is_headline' => 'boolean',
            'is_trending' => 'boolean',
            'views_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tag');
    }

    public function views(): HasMany
    {
        return $this->hasMany(ArticleView::class);
    }

    /**
     * Scope hanya artikel yang sudah dipublish dan waktunya sudah tiba.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', ArticleStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope artikel utama (headline).
     */
    public function scopeHeadline(Builder $query): Builder
    {
        return $query->published()->where('is_headline', true);
    }

    /**
     * URL penuh gambar dari S3 / Cloudflare R2 / MinIO.
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        if (! $this->featured_image) {
            return null;
        }

        if (str_starts_with($this->featured_image, 'http')) {
            return $this->featured_image;
        }

        return Storage::disk(config('filesystems.media_disk', 's3'))->url($this->featured_image);
    }
}
