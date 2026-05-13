<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Source: .planning/phases/03-games-match-types/03-06-PLAN.md task 2.
 *
 * Inline GameMatchType CRUD on the Game edit page (RESEARCH.md Pattern 1).
 *
 * Pattern 2 second-tier (RoleLimits) lives in plan 03-07's GameMatchTypeResource —
 * Filament v3 does NOT support nested RelationManagers, so RoleLimits are NOT
 * editable here. Plan 03-07 task 3 amends this file (Rule 2 amendment) to
 * override EditAction::make()->url(...) and navigate to GameMatchTypeResource's
 * edit page. Until then, EditAction is the default modal-based edit.
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
                // Plan 03-07 task 3 (Rule 2 amendment) replaces this with:
                //   Tables\Actions\EditAction::make()
                //       ->url(fn (GameMatchType $record) => GameMatchTypeResource::getUrl('edit', ['record' => $record]))
                // This plan ships the default modal-based EditAction since
                // GameMatchTypeResource does not exist yet (plan 03-07 wave 5).
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
