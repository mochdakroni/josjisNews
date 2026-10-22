<?php

namespace App\Filament\Resources;

use App\Models\Advertisement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdvertisementResource extends Resource
{
    protected static ?string $model = Advertisement::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = 'Redaksi & Konten';
    protected static ?string $navigationLabel = 'Advertise / Slot Iklan';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Kampanye Iklan')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Judul / Nama Kampanye Iklan')
                            ->required()
                            ->maxLength(180),

                        Forms\Components\TextInput::make('sponsor_name')
                            ->label('Nama Pengiklan / Sponsor')
                            ->maxLength(120),

                        Forms\Components\Select::make('position')
                            ->label('Posisi Slot di Halaman Depan / Artikel')
                            ->options(Advertisement::POSITIONS)
                            ->required(),

                        Forms\Components\Select::make('ad_type')
                            ->label('Jenis Materi Iklan')
                            ->options([
                                'image' => 'Banner Gambar + Link Tujuan',
                                'html'  => 'Kode HTML / Script Iklan Kustom',
                            ])
                            ->default('image')
                            ->live()
                            ->required(),

                        Forms\Components\TextInput::make('image_url')
                            ->label('URL / Path Gambar Banner')
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => $get('ad_type') === 'image'),

                        Forms\Components\TextInput::make('target_url')
                            ->label('Link URL Tujuan Saat Diklik')
                            ->url()
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => $get('ad_type') === 'image'),

                        Forms\Components\Textarea::make('html_code')
                            ->label('Kode HTML Banner')
                            ->rows(4)
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => $get('ad_type') === 'html'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Tayangkan Slot Iklan Ini (Aktif)')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Nama Iklan')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('sponsor_name')
                    ->label('Sponsor'),

                Tables\Columns\BadgeColumn::make('position')
                    ->label('Posisi Slot')
                    ->formatStateUsing(fn (string $state) => Advertisement::POSITIONS[$state] ?? $state),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status Tayang')
                    ->boolean(),

                Tables\Columns\TextColumn::make('views_count')
                    ->label('Tayangan')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('clicks_count')
                    ->label('Klik')
                    ->numeric()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
