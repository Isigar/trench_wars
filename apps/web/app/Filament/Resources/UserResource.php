<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Source: .planning/phases/01-foundations/01-13-PLAN.md task 1 + CONTEXT.md "P1 Filament resources".
 *
 * Users come via Discord OAuth only (D-002) — no Create page is registered.
 * `discord_id` is read-only and dehydrated(false) so the form never writes it
 * (T-1-28 mitigation; only the OAuth callback in plan 09 mutates it).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('admin.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.user.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('discord_id')
                ->label(__('admin.user.fields.discord_id'))
                ->disabled()
                ->dehydrated(false),
            Forms\Components\TextInput::make('username')
                ->label(__('admin.user.fields.username'))
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('email')
                ->label(__('admin.user.fields.email'))
                ->email()
                ->maxLength(255),
            Forms\Components\TextInput::make('avatar_url')
                ->label(__('admin.user.fields.avatar_url'))
                ->url()
                ->maxLength(2048),
            Forms\Components\Select::make('locale')
                ->label(__('admin.user.fields.locale'))
                ->options(self::localeOptions())
                ->default('en')
                ->required(),
            Forms\Components\DateTimePicker::make('last_login_at')
                ->label(__('admin.user.fields.last_login_at'))
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('discord_id')
                    ->label(__('admin.user.fields.discord_id'))
                    ->copyable()
                    ->searchable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('username')
                    ->label(__('admin.user.fields.username'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('admin.user.fields.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('admin.user.fields.last_login_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('last_login_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    /**
     * Locale codes the panel can offer for User->locale, sourced from config/i18n.php.
     *
     * @return array<string, string>
     */
    private static function localeOptions(): array
    {
        /** @var array<int, string> $locales */
        $locales = config('i18n.available_locales', ['en']);
        $options = [];
        foreach ($locales as $code) {
            $options[$code] = $code;
        }

        return $options;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            // INTENTIONALLY no 'create' route — users come via Discord OAuth (D-002).
        ];
    }
}
