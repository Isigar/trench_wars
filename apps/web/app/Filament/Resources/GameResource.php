<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GameResource\Pages;
use App\Filament\Resources\GameResource\RelationManagers;
use App\Models\Game;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

/**
 * Source: .planning/phases/03-games-match-types/03-06-PLAN.md task 1.
 *
 * GameResource — admin CRUD for the generic Game catalogue (D-007, D-012).
 * Form uses Tabs: Profile (key + translatable name + is_active) + Audit tab.
 * RelationManagers (Roles, MatchTypes) attach via getRelations().
 *
 * Pitfall 2 (RESEARCH.md): null translatable JSONB submission is coerced by
 * CreateGame::mutateFormDataBeforeCreate + EditGame::mutateFormDataBeforeSave.
 *
 * Pitfall 8: navigationSort=10 keeps Games AFTER all Phase 1/2 resources.
 *
 * T-03-06-03: `key` is immutable post-create — admin edits would break the
 * GameSeeder firstOrCreate idempotency contract. `disabledOn('edit')` enforces.
 */
class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('admin.game.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.game.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('game_tabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make(__('admin.game.tab.profile'))
                        ->icon('heroicon-o-puzzle-piece')
                        ->schema([
                            Section::make(__('admin.game.section.profile'))
                                ->schema([
                                    // T-03-06-03: key is immutable on edit (seeder idempotency contract).
                                    Forms\Components\TextInput::make('key')
                                        ->label(__('admin.game.fields.key'))
                                        ->required()
                                        ->maxLength(64)
                                        ->regex('/^[a-z0-9_]+$/')
                                        ->helperText(__('admin.game.help.key_format'))
                                        ->disabledOn('edit'),

                                    // name is JSONB locale-keyed via HasTranslations.
                                    // Pitfall 2: ->default(['en' => '']) prevents null submission.
                                    Forms\Components\KeyValue::make('name')
                                        ->label(__('admin.game.fields.name'))
                                        ->keyLabel(__('admin.game.fields.name_locale'))
                                        ->valueLabel(__('admin.game.fields.name_text'))
                                        ->reorderable(false)
                                        ->default(['en' => ''])
                                        ->required(),

                                    Forms\Components\Toggle::make('is_active')
                                        ->label(__('admin.game.fields.is_active'))
                                        ->default(true),
                                ]),
                        ]),

                    Forms\Components\Tabs\Tab::make(__('admin.game.tab.audit'))
                        ->icon('heroicon-o-archive-box')
                        ->schema([
                            Forms\Components\Placeholder::make('audit_log')
                                ->label('')
                                ->content(fn ($record): View|string => $record !== null
                                    ? view('filament.partials.audit-tab', ['subject' => $record])
                                    : (string) __('admin.audit.no_activity_yet')),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label(__('admin.game.fields.key'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.game.fields.name'))
                    ->getStateUsing(fn ($record): string => is_array($record->name) ? ($record->name['en'] ?? '—') : '—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.game.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.player.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // INTENTIONALLY no DeleteAction — deletion cascades to roles + match-types + role-limits.
                // Admin uses is_active toggle to hide a game (Open Question Q4 — defer hard-delete).
            ]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            RelationManagers\RolesRelationManager::class,
            RelationManagers\MatchTypesRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'view' => Pages\ViewGame::route('/{record}'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }
}
