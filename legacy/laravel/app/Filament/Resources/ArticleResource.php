<?php

namespace App\Filament\Resources;

use App\Enums\ArticleStatus;
use App\Enums\UserRole;
use App\Filament\Forms\Components\CKEditor;
use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Redaksi & Konten';

    /**
     * Mengambil daftar file gambar yang sudah pernah diunggah di direktori Storage/S3/R2.
     */
    protected static function getExistingMediaImages(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.media_disk', 's3'));
            $files = array_merge(
                $disk->allFiles('articles/featured'),
                $disk->allFiles('articles/images')
            );

            $options = [];
            foreach ($files as $file) {
                $options[$file] = basename($file) . ' (' . $file . ')';
            }

            return $options;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function form(Form $form): Form
    {
        $user = Auth::user();
        $canPublish = $user?->role?->canPublish() ?? false;

        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Konten Berita & Multimedia')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Judul Artikel')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) =>
                                        $operation === 'create' ? $set('slug', Str::slug($state)) : null
                                    ),

                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(280)
                                    ->unique(Article::class, 'slug', ignoreRecord: true),

                                Forms\Components\Textarea::make('excerpt')
                                    ->label('Ringkasan (Excerpt)')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->columnSpanFull(),

                                CKEditor::make('content_body')
                                    ->label('Isi Berita & Multimedia (Gambar, Video, PDF, & Link Google Drive)')
                                    ->required()
                                    ->uploadUrl('/admin/media/ckeditor-upload')
                                    ->minHeight(440)
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Forms\Components\Section::make('Gambar Utama (Unggah Baru, Pilih dari Direktori, atau Tempel Link URL)')
                            ->schema([
                                Forms\Components\Radio::make('featured_image_source')
                                    ->label('Sumber Gambar Utama')
                                    ->options([
                                        'upload' => 'Unggah Gambar Baru',
                                        'library' => 'Pilih dari Direktori Media (Sudah Pernah Diunggah)',
                                        'url' => 'Tempel Link Gambar (URL / Google Drive)',
                                    ])
                                    ->default('upload')
                                    ->inline()
                                    ->dehydrated(false)
                                    ->live(),

                                // Opsi 1: Unggah Gambar Baru
                                Forms\Components\FileUpload::make('featured_image_upload')
                                    ->label('Unggah File Gambar Baru')
                                    ->image()
                                    ->disk(config('filesystems.media_disk', 's3'))
                                    ->directory('articles/featured')
                                    ->visibility('public')
                                    ->maxSize(5120)
                                    ->dehydrated(false)
                                    ->visible(fn (Forms\Get $get): bool => $get('featured_image_source') === 'upload')
                                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $state ? $set('featured_image', $state) : null),

                                // Opsi 2: Pilih dari Direktori Media yang sudah pernah diunggah
                                Forms\Components\Select::make('featured_image_library')
                                    ->label('Pilih Gambar dari Direktori Server/S3')
                                    ->options(fn (): array => self::getExistingMediaImages())
                                    ->searchable()
                                    ->dehydrated(false)
                                    ->visible(fn (Forms\Get $get): bool => $get('featured_image_source') === 'library')
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $state ? $set('featured_image', $state) : null),

                                // Opsi 3: Tempel Link URL Gambar / Google Drive
                                Forms\Components\TextInput::make('featured_image')
                                    ->label('Path atau URL Gambar Utama Aktif')
                                    ->placeholder('https://... atau pilih dari opsi di atas')
                                    ->helperText('Otomatis terisi saat Anda mengunggah atau memilih dari direktori, atau tempel langsung URL gambar eksternal / Google Drive di sini.'),

                                Forms\Components\TextInput::make('caption_image')
                                    ->label('Caption / Kredit Foto')
                                    ->maxLength(255),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Publikasi & Klasifikasi')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options(
                                        $canPublish
                                            ? [
                                                ArticleStatus::DRAFT->value => ArticleStatus::DRAFT->label(),
                                                ArticleStatus::REVIEW->value => ArticleStatus::REVIEW->label(),
                                                ArticleStatus::PUBLISHED->value => ArticleStatus::PUBLISHED->label(),
                                            ]
                                            : [
                                                ArticleStatus::DRAFT->value => ArticleStatus::DRAFT->label(),
                                                ArticleStatus::REVIEW->value => ArticleStatus::REVIEW->label(),
                                            ]
                                    )
                                    ->default(ArticleStatus::DRAFT->value)
                                    ->required(),

                                Forms\Components\DateTimePicker::make('published_at')
                                    ->label('Waktu Tayang')
                                    ->disabled(! $canPublish),

                                Forms\Components\Select::make('category_id')
                                    ->label('Kategori')
                                    ->relationship('category', 'name', fn (Builder $query) => $query->where('is_active', true))
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('tags')
                                    ->label('Tag Berita')
                                    ->relationship('tags', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),
                                        Forms\Components\TextInput::make('slug')
                                            ->required()
                                            ->unique('tags', 'slug'),
                                    ]),

                                Forms\Components\Toggle::make('is_headline')
                                    ->label('Tampilkan di Headline Utama')
                                    ->disabled(! $canPublish),

                                Forms\Components\Toggle::make('is_trending')
                                    ->label('Pin sebagai Berita Trending')
                                    ->disabled(! $canPublish),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Gambar')
                    ->disk(config('filesystems.media_disk', 's3')),

                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Penulis')
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ArticleStatus $state): string => $state->label())
                    ->color(fn (ArticleStatus $state): string => $state->color()),

                Tables\Columns\IconColumn::make('is_headline')
                                    ->label('Headline')
                    ->boolean(),

                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Tayang')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        ArticleStatus::DRAFT->value => 'Draft',
                        ArticleStatus::REVIEW->value => 'Review',
                        ArticleStatus::PUBLISHED->value => 'Published',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_headline')
                    ->label('Headline'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user && $user->role === UserRole::JOURNALIST) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }
}
