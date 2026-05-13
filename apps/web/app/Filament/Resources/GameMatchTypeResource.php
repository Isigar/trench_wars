<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GameMatchTypeResource\Pages;
use App\Filament\Resources\GameMatchTypeResource\RelationManagers;
use App\Models\GameMatchType;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

/**
 * Source: .planning/phases/03-games-match-types/03-07-PLAN.md task 1.
 *
 * GameMatchTypeResource — second-tier admin CRUD for GameMatchType (RESEARCH.md Pattern 2).
 * Filament v3 does NOT support nested RelationManagers (RolesRelationManager inside
 * MatchTypesRelationManager inside GameResource), so this standalone resource is the
 * canonical workaround: admin clicks a MatchType from Game's edit page and lands here
 * to manage RoleLimits (via the RoleLimitsRelationManager attached below).
 *
 * Pattern 3 (RESEARCH.md): RoleLimitsRelationManager scopes its game_role_id Select
 * via getOwnerRecord()->game->roles() — admin cannot pick a cross-game role through UI.
 * The GameMatchTypeRoleLimit::saving() listener (plan 03-03) catches API/Console writes.
 *
 * Pitfall 2 (RESEARCH.md): TWO translatable JSONB fields (name + description) BOTH
 * require null-coercion in the Create + Edit page mutators.
 *
 * Pitfall 8 / Open Question Q3 RESOLVED (RESEARCH.md): navigationSort=11 keeps the
 * GameMatchType resource immediately AFTER GameResource (sort=10) in the sidebar.
 *
 * T-03-07-06 mitigation: `game_id` Select is required on Create — admin cannot land
 * an orphan MatchType. On Edit `game_id` is disabled (changing it would orphan all
 * existing RoleLimits whose role_id points at the old game's roles — admin must delete
 * + recreate if they truly need to move).
 */
class GameMatchTypeResource extends Resource
{
    protected static ?string $model = GameMatchType::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?int $navigationSort = 11;

    public static function getModelLabel(): string
    {
        return __('admin.game_match_type.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.game_match_type.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('game_match_type_tabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make(__('admin.game_match_type.tab.profile'))
                        ->icon('heroicon-o-list-bullet')
                        ->schema([
                            Section::make(__('admin.game_match_type.section.profile'))
                                ->schema([
                                    // T-03-07-06: parent Game must be picked on create.
                                    // disabledOn('edit') — changing game_id would orphan RoleLimits.
                                    Forms\Components\Select::make('game_id')
                                        ->label(__('admin.game_match_type.fields.game'))
                                        ->relationship('game', 'key')
                                        ->required()
                                        ->searchable()
                                        ->disabledOn('edit'),

                                    // T-03-06-03 / GameResource convention: key is immutable post-create.
                                    Forms\Components\TextInput::make('key')
                                        ->label(__('admin.game_match_type.fields.key'))
                                        ->required()
                                        ->maxLength(64)
                                        ->regex('/^[a-z0-9_]+$/')
                                        ->helperText(__('admin.game_match_type.help.key_format'))
                                        ->disabledOn('edit'),

                                    // name is JSONB locale-keyed via HasTranslations.
                                    // Pitfall 2: ->default(['en' => '']) prevents null submission;
                                    // CreateGameMatchType + EditGameMatchType mutators also coerce.
                                    Forms\Components\KeyValue::make('name')
                                        ->label(__('admin.game_match_type.fields.name'))
                                        ->keyLabel(__('admin.game_match_type.fields.name_locale'))
                                        ->valueLabel(__('admin.game_match_type.fields.name_text'))
                                        ->reorderable(false)
                                        ->default(['en' => ''])
                                        ->required(),

                                    // description is JSONB locale-keyed via HasTranslations (nullable in migration).
                                    // Pitfall 2 — second translatable field; Create/Edit mutators handle both.
                                    Forms\Components\KeyValue::make('description')
                                        ->label(__('admin.game_match_type.fields.description'))
                                        ->keyLabel(__('admin.game_match_type.fields.description_locale'))
                                        ->valueLabel(__('admin.game_match_type.fields.description_text'))
                                        ->reorderable(false)
                                        ->default(['en' => '']),

                                    Forms\Components\Toggle::make('is_active')
                                        ->label(__('admin.game_match_type.fields.is_active'))
                                        ->default(true),
                                ]),
                        ]),

                    Forms\Components\Tabs\Tab::make(__('admin.game_match_type.tab.audit'))
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
                Tables\Columns\TextColumn::make('game.key')
                    ->label(__('admin.game_match_type.fields.game'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('key')
                    ->label(__('admin.game_match_type.fields.key'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.game_match_type.fields.name'))
                    ->getStateUsing(fn ($record): string => is_array($record->name) ? ($record->name['en'] ?? '—') : '—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.game_match_type.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('game')
                    ->label(__('admin.game_match_type.fields.game'))
                    ->relationship('game', 'key'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // INTENTIONALLY no DeleteAction — deletion cascades to role_limits.
                // Admin uses is_active toggle (mirrors GameResource convention).
            ]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            RelationManagers\RoleLimitsRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGameMatchTypes::route('/'),
            'create' => Pages\CreateGameMatchType::route('/create'),
            // No 'view' route — keeps the resource tight (ClanTagResource precedent).
            'edit' => Pages\EditGameMatchType::route('/{record}/edit'),
        ];
    }
}
