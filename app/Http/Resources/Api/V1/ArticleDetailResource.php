<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content_body' => $this->content_body,
            'featured_image_url' => $this->featured_image_url,
            'caption_image' => $this->caption_image,
            'status' => $this->status?->value ?? $this->status,
            'is_headline' => $this->is_headline,
            'is_trending' => $this->is_trending,
            'views_count' => $this->views_count,
            'reading_time_minutes' => max(1, (int) ceil(str_word_count(strip_tags((string) $this->content_body)) / 200)),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'author' => new AuthorResource($this->whenLoaded('author')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
