<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-11-PLAN.md Task 1.
 *
 * READ-ONLY. Stages are owned by BracketGeneratorService (plan 06-06) and the
 * SwissGenerator (plan 06-07). The admin views them but does not author them —
 * inline editing of `ordinal` or `type` would invalidate the generator output.
 *
 * No headerActions (no CreateAction). Row actions: ViewAction only.
 *
 * T-06-11-04 mitigation: read-only RelationManagers prevent admin tampering of
 * the bracket structure via Filament UI; all writes flow through the generator
 * services with their idempotency + transactional invariants.
 *
 * Pitfall 3: $relationship MUST match Tournament::stages() HasMany method.
 */
class StagesRelationManager extends RelationManager
{
    protected static string $relationship = 'stages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.tournament_stage.plural_label');
    }

    public function form(Form $form): Form
    {
        // Form is required by the RelationManager contract even though the table
        // exposes no CreateAction / EditAction — keeping all fields disabled so an
        // accidental wiring surface stays harmless.
        return $form->schema([
            Forms\Components\TextInput::make('type')
                ->label(__('admin.tournament_stage.fields.type'))
                ->disabled(),

            Forms\Components\TextInput::make('ordinal')
                ->label(__('admin.tournament_stage.fields.ordinal'))
                ->numeric()
                ->disabled(),

            Forms\Components\TextInput::make('name')
                ->label(__('admin.tournament_stage.fields.name'))
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ordinal')
                    ->label(__('admin.tournament_stage.fields.ordinal'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.tournament_stage.fields.type'))
                    ->getStateUsing(fn ($record): string => (string) __('tournaments.stage_types.' . $record->type . '.label')),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.tournament_stage.fields.name'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('brackets_count')
                    ->label(__('admin.tournament_stage.fields.brackets_count'))
                    ->counts('brackets'),
            ])
            ->defaultSort('ordinal')
            // No headerActions — stages are immutable from the admin UI.
            ->actions([
                Tables\Actions\ViewAction::make(),
                // INTENTIONALLY no EditAction / DeleteAction (T-06-11-04 mitigation).
            ]);
    }
}
