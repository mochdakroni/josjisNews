<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Manajemen Pengguna & Akses';

    protected static ?string $navigationLabel = 'Tim Redaksi & User';

    /**
     * Hanya Super Admin yang dapat mengelola akun User/Tim Redaksi.
     */
    public static function canViewAny(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Profil & Hak Akses Redaksi')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->maxLength(150)
                            ->unique(User::class, 'email', ignoreRecord: true),

                        Forms\Components\Select::make('role')
                            ->label('Peran Redaksi (Role)')
                            ->options([
                                UserRole::SUPER_ADMIN->value => UserRole::SUPER_ADMIN->label(),
                                UserRole::EDITOR->value => UserRole::EDITOR->label(),
                                UserRole::JOURNALIST->value => UserRole::JOURNALIST->label(),
                            ])
                            ->default(UserRole::JOURNALIST->value)
                            ->required(),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Peran Redaksi')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::SUPER_ADMIN => 'danger',
                        UserRole::EDITOR => 'info',
                        UserRole::JOURNALIST => 'success',
                    }),

                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Total Artikel')
                    ->counts('articles')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        UserRole::SUPER_ADMIN->value => 'Super Admin',
                        UserRole::EDITOR->value => 'Editor',
                        UserRole::JOURNALIST->value => 'Jurnalis',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
