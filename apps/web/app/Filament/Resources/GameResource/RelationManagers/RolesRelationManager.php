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
 * Inline GameRole CRUD on the Game edit page (RESEARCH.md Pattern 1).
 *
 * Pitfall 3 mitigation: $relationship MUST match Game::roles() HasMany method
 * name EXACTLY — a typo silently renders an empty tab.
 */
class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.game_role.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->label(__('admin.game_role.fields.key'))
                ->required()
                ->maxLength(64)
                ->regex('/^[a-z0-9_]+$/')
                ->helperText(__('admin.game_role.help.key_format')),

            // display_name is JSONB locale-keyed via HasTranslations.
            // Pitfall 2: ->default(['en' => '']) prevents null submission.
            Forms\Components\KeyValue::make('display_name')
                ->label(__('admin.game_role.fields.display_name'))
                ->keyLabel(__('admin.game_role.fields.display_name_locale'))
                ->valueLabel(__('admin.game_role.fields.display_name_text'))
                ->reorderable(false)
                ->default(['en' => ''])
                ->required(),

            Forms\Components\TextInput::make('sort_order')
                ->label(__('admin.game_role.fields.sort_order'))
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->label(__('admin.game_role.fields.is_active'))
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('admin.game_role.fields.sort_order'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('key')
                    ->label(__('admin.game_role.fields.key'))
                    ->searchable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('admin.game_role.fields.display_name'))
                    ->getStateUsing(fn ($record): string => is_array($record->display_name) ? ($record->display_name['en'] ?? '—') : '—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.game_role.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
