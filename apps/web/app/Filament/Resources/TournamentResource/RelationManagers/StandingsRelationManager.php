<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\RelationManagers;

use App\Models\Tournament;
use App\Services\StandingsCalculatorService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-11-PLAN.md Task 1.
 *
 * READ-ONLY standings table + a single `recalculate` header action that calls
 * StandingsCalculatorService::recalculate (plan 06-09). Standings rows are owned
 * by the calculator strategies; admin tampering would race against the
 * BracketAdvancementService chain.
 *
 * The action callback receives the Livewire component instance via `$livewire`
 * (Filament v3.3 idiom). $livewire->ownerRecord is the parent Tournament.
 *
 * Pitfall 3: $relationship MUST match Tournament::standings() name exactly.
 */
class StandingsRelationManager extends RelationManager
{
    protected static string $relationship = 'standings';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.tournament_standing.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('rank')
                ->label(__('admin.tournament_standing.fields.rank'))
                ->disabled(),

            Forms\Components\TextInput::make('points')
                ->label(__('admin.tournament_standing.fields.points'))
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rank')
                    ->label(__('admin.tournament_standing.fields.rank'))
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('participant.clan.slug')
                    ->label(__('admin.tournament_standing.fields.participant_id'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('wins')
                    ->label(__('admin.tournament_standing.fields.wins'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('losses')
                    ->label(__('admin.tournament_standing.fields.losses'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('draws')
                    ->label(__('admin.tournament_standing.fields.draws'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('points')
                    ->label(__('admin.tournament_standing.fields.points'))
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('tiebreak_score')
                    ->label(__('admin.tournament_standing.fields.tiebreak_score'))
                    ->numeric(2),
            ])
            ->defaultSort('rank')
            ->headerActions([
                Tables\Actions\Action::make('recalculate')
                    ->label(__('admin.tournament.actions.recalculate_standings.label'))
                    ->color('info')
                    ->icon('heroicon-o-calculator')
                    ->requiresConfirmation()
                    ->modalHeading(__('tournaments.actions.recalculate_standings.modal_heading'))
                    ->modalDescription(__('tournaments.actions.recalculate_standings.modal_description'))
                    ->action(function (): void {
                        /** @var Tournament $tournament */
                        $tournament = $this->getOwnerRecord();
                        app(StandingsCalculatorService::class)->recalculate($tournament);
                        Notification::make()
                            ->success()
                            ->title(__('tournaments.actions.recalculate_standings.success'))
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // INTENTIONALLY no Edit / Delete — calculator owns the data.
            ]);
    }
}
