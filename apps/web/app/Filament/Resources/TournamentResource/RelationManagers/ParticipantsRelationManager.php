<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\RelationManagers;

use App\Models\TournamentParticipant;
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
 * Tournament <-> TournamentParticipant <-> Clan join — admins add Clans to a
 * tournament (CreateAction) and may forfeit/withdraw individual participants.
 *
 * A5 LOCKED inline (consistent with plan 06-05 + 06-09): forfeit + withdraw row
 * actions have IDENTICAL forward semantics — only the status string differs
 * (disqualified for forfeit; withdrawn for withdraw). Both stop the participant
 * from advancing in future matches; both retain past match results.
 *
 * Both actions write an activity_log row with withProperties(['reason' => 'forfeit'|'withdraw',
 * 'previous_status' => ...]). The `previous_status` enables an audit-driven
 * timeline reconstruction (Phase 9 polish).
 *
 * CLAUDE.md §10 + Phase 4 D-04-12-A: use $record->update(...) rather than
 * TournamentParticipant::query()->update(...) so the observer/audit chain fires.
 *
 * Pitfall 3: $relationship MUST match Tournament::participants() HasMany method
 * name EXACTLY — Filament resolves the relationship eagerly and throws on typo.
 */
class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.tournament_participant.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('clan_id')
                ->label(__('admin.tournament_participant.fields.clan_id'))
                ->relationship('clan', 'slug')
                ->required()
                ->searchable()
                ->preload(),

            Forms\Components\TextInput::make('seed')
                ->label(__('admin.tournament_participant.fields.seed'))
                ->numeric()
                ->minValue(1)
                ->nullable(),

            Forms\Components\Select::make('status')
                ->label(__('admin.tournament_participant.fields.status'))
                ->options([
                    'registered' => __('tournaments.participant_status.registered.label'),
                    'active' => __('tournaments.participant_status.active.label'),
                    'withdrawn' => __('tournaments.participant_status.withdrawn.label'),
                    'disqualified' => __('tournaments.participant_status.disqualified.label'),
                ])
                ->default('registered')
                ->required(),

            Forms\Components\TextInput::make('placement')
                ->label(__('admin.tournament_participant.fields.placement'))
                ->numeric()
                ->minValue(1)
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clan.slug')
                    ->label(__('admin.tournament_participant.fields.clan_id'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('seed')
                    ->label(__('admin.tournament_participant.fields.seed'))
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('admin.tournament_participant.fields.status'))
                    ->getStateUsing(fn ($record): string => (string) __('tournaments.participant_status.' . $record->status . '.label'))
                    ->colors([
                        'info' => fn ($state, $record): bool => $record->status === 'registered',
                        'success' => fn ($state, $record): bool => $record->status === 'active',
                        'warning' => fn ($state, $record): bool => $record->status === 'withdrawn',
                        'danger' => fn ($state, $record): bool => $record->status === 'disqualified',
                    ]),

                Tables\Columns\TextColumn::make('placement')
                    ->label(__('admin.tournament_participant.fields.placement'))
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('seed')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // A5 LOCKED inline — forfeit: status → 'disqualified'
                Tables\Actions\Action::make('forfeit')
                    ->label(__('admin.tournament.actions.forfeit.label'))
                    ->color('warning')
                    ->icon('heroicon-o-no-symbol')
                    ->visible(fn (TournamentParticipant $record): bool => in_array($record->status, ['registered', 'active'], true))
                    ->requiresConfirmation()
                    ->modalHeading(function (TournamentParticipant $record): string {
                        $clan = $record->clan;

                        return (string) __('tournaments.actions.forfeit.modal_heading', [
                            'clan' => $clan !== null ? $clan->slug : '?',
                        ]);
                    })
                    ->modalDescription(__('tournaments.actions.forfeit.modal_description'))
                    ->action(function (TournamentParticipant $record): void {
                        $previousStatus = $record->status;
                        // CLAUDE.md §10: use $record->update(...) so observers/audit fire.
                        $record->update(['status' => 'disqualified']);
                        activity()
                            ->causedBy(auth()->user())
                            ->performedOn($record)
                            ->withProperties([
                                'reason' => 'forfeit',
                                'previous_status' => $previousStatus,
                            ])
                            ->log('Participant forfeited');
                        Notification::make()
                            ->success()
                            ->title(__('tournaments.actions.forfeit.success'))
                            ->send();
                    }),

                // A5 LOCKED inline — withdraw: status → 'withdrawn'
                Tables\Actions\Action::make('withdraw')
                    ->label(__('admin.tournament.actions.withdraw.label'))
                    ->color('warning')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (TournamentParticipant $record): bool => in_array($record->status, ['registered', 'active'], true))
                    ->requiresConfirmation()
                    ->modalHeading(function (TournamentParticipant $record): string {
                        $clan = $record->clan;

                        return (string) __('tournaments.actions.withdraw.modal_heading', [
                            'clan' => $clan !== null ? $clan->slug : '?',
                        ]);
                    })
                    ->modalDescription(__('tournaments.actions.withdraw.modal_description'))
                    ->action(function (TournamentParticipant $record): void {
                        $previousStatus = $record->status;
                        $record->update(['status' => 'withdrawn']);
                        activity()
                            ->causedBy(auth()->user())
                            ->performedOn($record)
                            ->withProperties([
                                'reason' => 'withdraw',
                                'previous_status' => $previousStatus,
                            ])
                            ->log('Participant withdrew');
                        Notification::make()
                            ->success()
                            ->title(__('tournaments.actions.withdraw.success'))
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
