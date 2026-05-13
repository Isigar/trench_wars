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
 * READ-ONLY (T-06-11-04 mitigation). Brackets are owned by the generator
 * services + BracketAdvancementService (Phase 6 plan 06-08). Admin inline edits
 * would invalidate the advance chain.
 *
 * Surfaces the brackets via Tournament::brackets() HasManyThrough (added in
 * plan 06-11 as part of this surface).
 *
 * Pitfall 3: $relationship MUST match Tournament::brackets() name exactly.
 */
class BracketsRelationManager extends RelationManager
{
    protected static string $relationship = 'brackets';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.tournament_bracket.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('round_number')
                ->label(__('admin.tournament_bracket.fields.round_number'))
                ->numeric()
                ->disabled(),

            Forms\Components\TextInput::make('position')
                ->label(__('admin.tournament_bracket.fields.position'))
                ->numeric()
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('stage.ordinal')
                    ->label(__('admin.tournament_bracket.fields.stage'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('round_number')
                    ->label(__('admin.tournament_bracket.fields.round_number'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('position')
                    ->label(__('admin.tournament_bracket.fields.position'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('participantA.clan.slug')
                    ->label(__('admin.tournament_bracket.fields.participant_a_id'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('participantB.clan.slug')
                    ->label(__('admin.tournament_bracket.fields.participant_b_id'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('winnerParticipant.clan.slug')
                    ->label(__('admin.tournament_bracket.fields.winner_participant_id'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('match_id')
                    ->label(__('admin.tournament_bracket.fields.match_id'))
                    ->fontFamily('mono')
                    ->limit(8)
                    ->placeholder('—'),
            ])
            ->defaultSort('round_number')
            // No headerActions — brackets are generator-owned.
            ->actions([
                Tables\Actions\ViewAction::make(),
                // INTENTIONALLY no EditAction / DeleteAction (T-06-11-04 mitigation).
            ]);
    }
}
