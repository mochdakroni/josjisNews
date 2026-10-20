<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * Custom Filament Form Field untuk CKEditor 5 (Classic / Newsroom Build)
 * Mendukung Heading, Bold, Italic, Link, Blockquote, Tabel, List, Media Embed (YouTube),
 * serta Upload Gambar langsung ke S3 / Cloudflare R2 / MinIO melalui endpoint internal.
 */
class CKEditor extends Field
{
    protected string $view = 'filament.forms.components.ckeditor';

    protected ?string $uploadUrl = null;

    protected int $minHeight = 420;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadUrl = '/admin/media/ckeditor-upload';
    }

    public function uploadUrl(string $url): static
    {
        $this->uploadUrl = $url;

        return $this;
    }

    public function getUploadUrl(): ?string
    {
        return $this->uploadUrl;
    }

    public function minHeight(int $pixels): static
    {
        $this->minHeight = $pixels;

        return $this;
    }

    public function getMinHeight(): int
    {
        return $this->minHeight;
    }
}
