<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\Pages;

use App\Filament\Base\EditRecord;
use App\Filament\Resources\TournamentResource;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Services\BracketMatchMaterialiserService;
use App\Services\Brackets\BracketGeneratorService;
use App\Services\Brackets\SwissGenerator;
use App\Services\StandingsCalculatorService;
use App\Services\TournamentSeedingService;
use App\Services\TournamentStatusService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-11-PLAN.md Task 1.
 *
 * 8 HeaderActions wire the full SC-1 / SC-2 / SC-5 admin flow. Every action
 * `requiresConfirmation()` with i18n-keyed modal copy. Each action's `visible()`
 * closure guards on tournament.status / format / bracket state so only the
 * applicable buttons render at any given lifecycle point.
 *
 * Actions (in render order on the page):
 *   1. open_registration           draft → registering
 *   2. seed                        registering → seeded (with strategy select)
 *   3. start                       seeded → running (generate brackets + materialise round 1)
 *   4. reseed                      seeded → registering → seeded (back-transition + new seeds)
 *   5. generate_next_swiss_round   running (swiss) — admin-click next round
 *   6. materialise_next_round      running (single/double-elim) — lazy round materialisation
 *   7. recalculate_standings       running|completed — wipe + recompute standings
 *   8. cancel                      not-terminal → cancelled
 *
 * Pitfall 7 / T-06-11-02: status field is `->disabledOn('edit')` in TournamentResource::form().
 * All transitions route through TournamentStatusService inside these action callbacks
 * (service enforces invariants + writes activity_log row).
 *
 * Open Question A6 LOCKED inline (admin-click swiss next-round): generate_next_swiss_round
 * is only visible when the latest swiss-round stage has all brackets resolved (winners
 * recorded). isSwissRoundComplete() helper enforces this guard.
 *
 * Coerce null translatable JSONB fields (title, description) to ['en' => ''] in
 * mutateFormDataBeforeSave to match the Phase 4 plan 04-09 idiom (Pitfall 2).
 */
class EditTournament extends EditRecord
{
    protected static string $resource = TournamentResource::class;

    /**
     * Coerce null translatable JSONB fields to ['en' => ''] before DB write.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['title'] = $data['title'] ?: ['en' => ''];
        $data['description'] = $data['description'] ?: ['en' => ''];

        return $data;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // --------------------------------------------------------------
            // 1. open_registration — draft → registering
            // --------------------------------------------------------------
            Action::make('open_registration')
                ->label(__('admin.tournament.actions.open_registration.label'))
                ->color('info')
                ->icon('heroicon-o-lock-open')
                ->requiresConfirmation()
                ->modalHeading(__('tournaments.actions.open_registration.modal_heading'))
                ->modalDescription(__('tournaments.actions.open_registration.modal_description'))
                ->visible(fn (Tournament $record): bool => $record->status === 'draft')
                ->action(function (Tournament $record): void {
                    $causer = auth()->user();
                    app(TournamentStatusService::class)->transition($record, 'registering', $causer);
                    Notification::make()
                        ->success()
                        ->title(__('tournaments.actions.open_registration.success'))
                        ->send();
                }),

            // --------------------------------------------------------------
            // 2. seed — registering → seeded (with strategy form)
            // --------------------------------------------------------------
            Action::make('seed')
                ->label(__('admin.tournament.actions.seed.label'))
                ->color('primary')
                ->icon('heroicon-o-bars-3-bottom-left')
                ->visible(fn (Tournament $record): bool => $record->status === 'registering'
                    && $record->participants()->where('status', 'registered')->count() >= 2)
                ->form([
                    Forms\Components\Select::make('strategy')
                        ->label('Strategy')
                        ->options([
                            'by_rank' => 'By rank',
                            'random' => 'Random',
                            'manual' => 'Manual (use admin-entered seed values)',
                        ])
                        ->default('random')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading(fn (Tournament $record): string => (string) __('tournaments.actions.seed.modal_heading', [
                    'count' => $record->participants()->where('status', 'registered')->count(),
                ]))
                ->modalDescription(__('tournaments.actions.seed.modal_description'))
                ->action(function (Tournament $record, array $data): void {
                    $causer = auth()->user();
                    app(TournamentSeedingService::class)->seed($record, $data['strategy'], $causer);
                    app(TournamentStatusService::class)->transition($record->refresh(), 'seeded', $causer);
                    Notification::make()
                        ->success()
                        ->title(__('tournaments.actions.seed.success'))
                        ->send();
                }),

            // --------------------------------------------------------------
            // 3. start — seeded → running (generate brackets + materialise round 1)
            // --------------------------------------------------------------
            Action::make('start')
                ->label(__('admin.tournament.actions.start.label'))
                ->color('success')
                ->icon('heroicon-o-play')
                ->visible(fn (Tournament $record): bool => $record->status === 'seeded')
                ->requiresConfirmation()
                ->modalHeading(__('tournaments.actions.start.modal_heading'))
                ->modalDescription(__('tournaments.actions.start.modal_description'))
                ->action(function (Tournament $record): void {
                    $causer = auth()->user();
                    // 1. generate the bracket tree (stages + brackets)
                    app(BracketGeneratorService::class)->generate($record);
                    // 2. transition status to running
                    app(TournamentStatusService::class)->transition($record->refresh(), 'running', $causer);
                    // 3. materialise round-1 GameMatch + slots for every non-bye bracket
                    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($record->refresh());
                    Notification::make()
                        ->success()
                        ->title(__('tournaments.actions.start.success'))
                        ->send();
                }),

            // --------------------------------------------------------------
            // 4. reseed — seeded ↔ registering ↔ seeded (only while canReseed())
            // --------------------------------------------------------------
            Action::make('reseed')
                ->label(__('admin.tournament.actions.reseed.label'))
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (Tournament $record): bool => $record->canReseed())
                ->form([
                    Forms\Components\Select::make('strategy')
                        ->label('Strategy')
                        ->options([
                            'by_rank' => 'By rank',
                            'random' => 'Random',
                            'manual' => 'Manual (use admin-entered seed values)',
                        ])
                        ->default('random')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading(__('tournaments.actions.reseed.modal_heading'))
                ->modalDescription(__('tournaments.actions.reseed.modal_description'))
                ->action(function (Tournament $record, array $data): void {
                    $causer = auth()->user();
                    app(TournamentSeedingService::class)->reseed($record, $data['strategy'], $causer);
                    Notification::make()
                        ->success()
                        ->title(__('tournaments.actions.reseed.success'))
                        ->send();
                }),

            // --------------------------------------------------------------
            // 5. generate_next_swiss_round — running + swiss + last round complete
            // --------------------------------------------------------------
            Action::make('generate_next_swiss_round')
                ->label(__('admin.tournament.actions.generate_next_swiss_round.label'))
                ->color('primary')
                ->icon('heroicon-o-forward')
                ->visible(fn (Tournament $record): bool => $record->status === 'running'
                    && $record->format === 'swiss'
                    && $this->isSwissRoundComplete($record))
                ->requiresConfirmation()
                ->modalHeading(__('tournaments.actions.generate_next_swiss_round.modal_heading'))
                ->modalDescription(__('tournaments.actions.generate_next_swiss_round.modal_description'))
                ->action(function (Tournament $record): void {
                    app(SwissGenerator::class)->generateNextRound($record);
                    // The new round's brackets are written but not yet materialised — fire
                    // the materialiser to spawn GameMatch + slots for the new pairings.
                    // materialiseFirstRound is misnamed historically — it materialises ANY
                    // bracket with both participants set AND match_id=null + round_number=1
                    // within each stage. Swiss stores each round in its own stage with
                    // round_number=1 inside the stage; that path holds.
                    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($record->refresh());
                    Notification::make()
                        ->success()
                        ->title(__('tournaments.actions.generate_next_swiss_round.success'))
                        ->send();
                }),

            // --------------------------------------------------------------
            // 6. materialise_next_round — running + non-swiss/non-round-robin + has un-materialised
            // --------------------------------------------------------------
            Action::make('materialise_next_round')
                ->label(__('admin.tournament.actions.materialise_next_round.label'))
                ->color('info')
                ->icon('heroicon-o-cube')
                ->visible(fn (Tournament $record): bool => $record->status === 'running'
                    && ! in_array($record->format, ['swiss', 'round_robin'], true)
                    && $this->hasUnmaterialisedBrackets($record))
                ->requiresConfirmation()
                ->modalHeading(__('admin.tournament.actions.materialise_next_round.modal_heading'))
                ->modalDescription(__('admin.tournament.actions.materialise_next_round.modal_description'))
                ->action(function (Tournament $record): void {
                    // Iterate brackets with both participants set + no match yet, call
                    // materialiseFor per bracket (idempotent for any already-materialised).
                    $stageIds = $record->stages()->pluck('id');
                    $brackets = TournamentBracket::query()
                        ->whereIn('tournament_stage_id', $stageIds)
                        ->whereNotNull('participant_a_id')
                        ->whereNotNull('participant_b_id')
                        ->whereNull('match_id')
                        ->get();
                    foreach ($brackets as $bracket) {
                        app(BracketMatchMaterialiserService::class)->materialiseFor($bracket, $record);
                    }
                    Notification::make()
                        ->success()
                        ->title(__('admin.tournament.actions.materialise_next_round.success'))
                        ->send();
                }),

            // --------------------------------------------------------------
            // 7. recalculate_standings — running|completed → wipe + recompute
            // --------------------------------------------------------------
            Action::make('recalculate_standings')
                ->label(__('admin.tournament.actions.recalculate_standings.label'))
                ->color('info')
                ->icon('heroicon-o-calculator')
                ->visible(fn (Tournament $record): bool => in_array($record->status, ['running', 'completed'], true))
                ->requiresConfirmation()
                ->modalHeading(__('tournaments.actions.recalculate_standings.modal_heading'))
                ->modalDescription(__('tournaments.actions.recalculate_standings.modal_description'))
                ->action(function (Tournament $record): void {
                    app(StandingsCalculatorService::class)->recalculate($record);
                    Notification::make()
                        ->success()
                        ->title(__('tournaments.actions.recalculate_standings.success'))
                        ->send();
                }),

            // --------------------------------------------------------------
            // 8. cancel — not-terminal → cancelled
            // --------------------------------------------------------------
            Action::make('cancel')
                ->label(__('admin.tournament.actions.cancel.label'))
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (Tournament $record): bool => ! in_array($record->status, ['completed', 'cancelled'], true))
                ->requiresConfirmation()
                ->modalHeading(__('tournaments.actions.cancel.modal_heading'))
                ->modalDescription(__('tournaments.actions.cancel.modal_description'))
                ->action(function (Tournament $record): void {
                    $causer = auth()->user();
                    app(TournamentStatusService::class)->transition($record, 'cancelled', $causer);
                    Notification::make()
                        ->success()
                        ->title(__('tournaments.actions.cancel.success'))
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->visible(fn (Tournament $record): bool => $record->status === 'draft'),
        ];
    }

    /**
     * Returns true when the latest swiss-round stage has zero brackets with
     * winner_participant_id still null. The admin can then generate the next round.
     */
    private function isSwissRoundComplete(Tournament $tournament): bool
    {
        $lastSwissStage = $tournament->stages()
            ->where('type', 'swiss-round')
            ->orderByDesc('ordinal')
            ->first();

        if ($lastSwissStage === null) {
            return false;
        }

        return ! $lastSwissStage->brackets()->whereNull('winner_participant_id')->exists();
    }

    /**
     * Returns true when the tournament has at least one bracket with both
     * participants assigned but no GameMatch yet. The admin can then click
     * "Materialise next round" to spawn the matches.
     */
    private function hasUnmaterialisedBrackets(Tournament $tournament): bool
    {
        $stageIds = $tournament->stages()->pluck('id');

        return TournamentBracket::query()
            ->whereIn('tournament_stage_id', $stageIds)
            ->whereNotNull('participant_a_id')
            ->whereNotNull('participant_b_id')
            ->whereNull('match_id')
            ->exists();
    }
}
