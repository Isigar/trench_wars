<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameResource\RelationManagers;

use App\Filament\Resources\GameMatchTypeResource;
use App\Models\GameMatchType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Source: .planning/phases/03-games-match-types/03-06-PLAN.md task 2.
 * Amended: .planning/phases/03-games-match-types/03-07-PLAN.md task 3 (Rule 2).
 *
 * Inline GameMatchType CRUD on the Game edit page (RESEARCH.md Pattern 1) +
 * Pattern 2 click-through to GameMatchTypeResource for RoleLimits management.
 *
 * Filament v3 does NOT support nested RelationManagers (RolesRelationManager
 * inside MatchTypesRelationManager inside GameResource), so RoleLimits are
 * managed via the standalone GameMatchTypeResource (plan 03-07). EditAction
 * navigates there via `->url(fn ($record) => GameMatchTypeResource::getUrl('edit'))`.
 *
 * Pitfall 3 mitigation: $relationship MUST match Game::matchTypes() HasMany.
 */
class MatchTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'matchTypes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.game_match_type.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->label(__('admin.game_match_type.fields.key'))
                ->required()
                ->maxLength(64)
                ->regex('/^[a-z0-9_]+$/')
                ->helperText(__('admin.game_match_type.help.key_format')),

            // name is JSONB locale-keyed via HasTranslations.
            Forms\Components\KeyValue::make('name')
                ->label(__('admin.game_match_type.fields.name'))
                ->keyLabel(__('admin.game_match_type.fields.name_locale'))
                ->valueLabel(__('admin.game_match_type.fields.name_text'))
                ->reorderable(false)
                ->default(['en' => ''])
                ->required(),

            // description is JSONB locale-keyed via HasTranslations (nullable in migration).
            Forms\Components\KeyValue::make('description')
                ->label(__('admin.game_match_type.fields.description'))
                ->keyLabel(__('admin.game_match_type.fields.description_locale'))
                ->valueLabel(__('admin.game_match_type.fields.description_text'))
                ->reorderable(false)
                ->default(['en' => '']),

            Forms\Components\Toggle::make('is_active')
                ->label(__('admin.game_match_type.fields.is_active'))
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label(__('admin.game_match_type.fields.key'))
                    ->searchable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.game_match_type.fields.name'))
                    ->getStateUsing(fn ($record): string => is_array($record->name) ? ($record->name['en'] ?? '—') : '—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.game_match_type.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('key')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // Pattern 2 click-through (RESEARCH.md): override EditAction's default
                // modal to navigate to the standalone GameMatchTypeResource edit page,
                // where the RoleLimits RelationManager (plan 03-07) lives.
                //
                // Plan 03-07 task 3 Rule-2 amendment — supersedes plan 03-06's
                // default modal-based EditAction (deferred behind GameMatchTypeResource
                // landing in wave 5).
                Tables\Actions\EditAction::make()
                    ->url(fn (GameMatchType $record): string => GameMatchTypeResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
