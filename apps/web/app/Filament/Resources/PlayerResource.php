<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerResource\Pages;
use App\Models\Player;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

/**
 * Source: .planning/phases/01-foundations/01-13-PLAN.md task 1 + CONTEXT.md "P1 Filament resources".
 *
 * Inline `player_privacy` Section bound via ->relationship('privacy') so a single
 * form save persists both the Player row and its 1:1 PlayerPrivacy row.
 *
 * No Create page — Players are minted at first Discord login (plan 09).
 */
class PlayerResource extends Resource
{
    protected static ?string $model = Player::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('admin.player.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.player.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('player_tabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make(__('admin.tab.profile'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            Section::make(__('admin.player.section.profile'))
                                ->schema([
                                    Forms\Components\Select::make('user_id')
                                        ->label(__('admin.player.fields.user'))
                                        ->relationship('user', 'username')
                                        ->required()
                                        ->disabled()
                                        ->dehydrated(false),
                                    Forms\Components\TextInput::make('slug')
                                        ->label(__('admin.player.fields.slug'))
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('display_name')
                                        ->label(__('admin.player.fields.display_name'))
                                        ->maxLength(255),
                                    Forms\Components\Select::make('avatar_source')
                                        ->label(__('admin.player.fields.avatar_source'))
                                        ->options([
                                            'discord' => 'discord',
                                            'upload' => 'upload',
                                        ])
                                        ->default('discord')
                                        ->required(),
                                    Forms\Components\TextInput::make('avatar_path')
                                        ->label(__('admin.player.fields.avatar_path'))
                                        ->maxLength(2048),
                                    Forms\Components\TextInput::make('country_code')
                                        ->label(__('admin.player.fields.country_code'))
                                        ->maxLength(2),
                                    // bio is JSONB with an Eloquent `array` cast (locale → text).
                                    // Textarea would coerce the form input to a string scalar and
                                    // silently corrupt the locale-keyed shape. Use KeyValue to
                                    // round-trip the array until Phase 2's structured editor lands.
                                    Forms\Components\KeyValue::make('bio')
                                        ->label(__('admin.player.fields.bio'))
                                        ->keyLabel(__('admin.player.fields.bio_locale'))
                                        ->valueLabel(__('admin.player.fields.bio_text'))
                                        ->reorderable(false)
                                        ->helperText(__('admin.player.help.bio_jsonb')),
                                ]),

                            Section::make(__('admin.player.section.privacy'))
                                ->relationship('privacy')
                                ->schema([
                                    Forms\Components\Select::make('show_to')
                                        ->label(__('admin.player.fields.show_to'))
                                        ->options([
                                            'public' => 'public',
                                            'community' => 'community',
                                            'clan' => 'clan',
                                            'private' => 'private',
                                        ])
                                        ->required(),
                                    Forms\Components\Toggle::make('show_real_name')
                                        ->label(__('admin.player.fields.show_real_name')),
                                    Forms\Components\Toggle::make('show_discord_tag')
                                        ->label(__('admin.player.fields.show_discord_tag')),
                                    Forms\Components\Toggle::make('show_clan_history')
                                        ->label(__('admin.player.fields.show_clan_history')),
                                    Forms\Components\Toggle::make('show_match_history')
                                        ->label(__('admin.player.fields.show_match_history')),
                                    Forms\Components\Toggle::make('show_stats')
                                        ->label(__('admin.player.fields.show_stats')),
                                ])
                                ->columns(2),
                        ]),

                    Forms\Components\Tabs\Tab::make(__('admin.tab.audit'))
                        ->icon('heroicon-o-archive-box')
                        ->schema([
                            Forms\Components\Placeholder::make('audit_log')
                                ->label('')
                                ->content(fn ($record): View => view('filament.partials.audit-tab', [
                                    'subject' => $record,
                                ])),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('admin.player.fields.slug'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.player.fields.user'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->label(__('admin.player.fields.country_code')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.player.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayers::route('/'),
            'view' => Pages\ViewPlayer::route('/{record}'),
            'edit' => Pages\EditPlayer::route('/{record}/edit'),
            // INTENTIONALLY no 'create' route — Players come via first-login (plan 09).
        ];
    }
}
