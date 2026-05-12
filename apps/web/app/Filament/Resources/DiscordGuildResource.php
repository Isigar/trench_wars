<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DiscordGuildResource\Pages;
use App\Models\DiscordGuild;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Source: .planning/phases/02-clans-tags/02-13-PLAN.md task 2.
 *
 * D-003: discord_guild holds exactly one row for the league's Discord guild.
 * Seeder (plan 02-04) populates the stub row; admin fills guild_id + name after bot setup.
 *
 * CRITICAL: getPages() INTENTIONALLY omits 'create' route to enforce single-row invariant.
 * Visiting /admin/discord-guilds/create returns 404.
 * This is the Filament-layer enforcement (T-02-10-02 mitigation, belt + suspenders with DB seeder).
 */
class DiscordGuildResource extends Resource
{
    protected static ?string $model = DiscordGuild::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return __('admin.discord_guild.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.discord_guild.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('guild_id')
                ->label(__('admin.discord_guild.fields.guild_id'))
                ->required()
                ->maxLength(32)
                ->regex('/^[0-9]+$/')
                ->helperText('Discord snowflake — numeric string, up to 19 digits.'),

            Forms\Components\TextInput::make('name')
                ->label(__('admin.discord_guild.fields.name'))
                ->maxLength(255),

            Forms\Components\TextInput::make('icon_url')
                ->label(__('admin.discord_guild.fields.icon_url'))
                ->url()
                ->nullable()
                ->maxLength(2048),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('guild_id')
                    ->label(__('admin.discord_guild.fields.guild_id'))
                    ->fontFamily('mono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.discord_guild.fields.name'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('icon_url')
                    ->label(__('admin.discord_guild.fields.icon_url'))
                    ->url(fn ($record): ?string => $record->icon_url)
                    ->limit(60),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                // No DeleteAction — preserve the D-003 singleton row (T-02-10-02 mitigation).
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscordGuilds::route('/'),
            // INTENTIONALLY no 'create' route — discord_guild holds exactly one row (D-003).
            // Seeder populates the row (DiscordGuildSeeder, plan 02-04); admin fills fields via edit only.
            'edit' => Pages\EditDiscordGuild::route('/{record}/edit'),
        ];
    }
}
