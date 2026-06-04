<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\RelationManagers;

use App\Models\Tournament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-11-PLAN.md Task 1.
 *         + .planning/phases/11-tournament-depth/11-04-PLAN.md Task 2 (TOUR-04).
 *
 * MOSTLY READ-ONLY. Stages are owned by BracketGeneratorService (plan 06-06) and
 * the SwissGenerator (plan 06-07). The admin views them but does not author them —
 * inline editing of `ordinal`, `type`, or `name` would invalidate the generator
 * output.
 *
 * TOUR-04 exception: game_match_type_id is editable. This is the stage-level
 * override — admin can set a different GameMatchType per stage, scoped to the
 * tournament's own game (Pattern 3 cross-game guard). ordinal/type/name stay
 * disabled (T-06-11-04 invariant preserved).
 *
 * Row actions: ViewAction + EditAction (game_match_type_id only).
 * No headerActions (no CreateAction). No DeleteAction.
 *
 * T-11-04-01 mitigation: Select options scoped to stage.tournament.game.matchTypes —
 * admin cannot pick a type from a different game via the UI.
 * T-11-04-03 mitigation: ordinal/type/name remain ->disabled in the form.
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
        return $form->schema([
            // Structure fields — remain disabled (T-06-11-04 invariant: generator owns stage structure).
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

            // TOUR-04: editable match-type override scoped to the tournament's game.
            // Pattern 3 (mirrors RoleLimitsRelationManager): getOwnerRecord() resolves
            // the parent Tournament, then ->game yields ONLY that tournament's game's
            // match types. Admin cannot pick a type from a different game (T-11-04-01).
            Forms\Components\Select::make('game_match_type_id')
                ->label(__('admin.tournament_stage.fields.game_match_type_id'))
                ->options(function (RelationManager $livewire): array {
                    /** @var Tournament $tournament */
                    $tournament = $livewire->getOwnerRecord();
                    $game = $tournament->game;

                    if ($game === null) {
                        return [];
                    }

                    return $game->matchTypes()
                        ->orderBy('key')
                        ->get()
                        ->mapWithKeys(fn ($mt): array => [$mt->id => $mt->key])
                        ->toArray();
                })
                ->nullable()
                ->searchable(),
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

                // TOUR-04: effective stage-level match type override column.
                // Shows null/inherited as '—' (placeholder) via the gameMatchType relation
                // added by plan 11-01.
                Tables\Columns\TextColumn::make('gameMatchType.key')
                    ->label(__('admin.tournament_stage.fields.game_match_type_id'))
                    ->placeholder('—'),
            ])
            ->defaultSort('ordinal')
            // No headerActions — stages are immutable from the admin UI.
            ->actions([
                Tables\Actions\ViewAction::make(),
                // TOUR-04: EditAction exposes ONLY the game_match_type_id Select.
                // ordinal/type/name stay read-only via the form's ->disabled() rules
                // (T-06-11-04 mitigation — generator output stays authoritative).
                Tables\Actions\EditAction::make(),
            ]);
    }
}
