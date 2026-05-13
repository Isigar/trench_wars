<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TournamentResource\Pages;
use App\Filament\Resources\TournamentResource\RelationManagers;
use App\Models\Tournament;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-11-PLAN.md Task 1.
 *
 * TournamentResource — admin CRUD for the Tournament aggregate (D-012).
 *
 * Navigation: group='Tournaments' (NEW Phase 6 sidebar group, sort=30 after Phase 4
 * MatchResource=20). Mirrors Phase 4 MatchResource (plan 04-09) — Tabs (Profile +
 * Audit) idiom (D-04-12-B); 4 RelationManagers (Participants mutable; Stages /
 * Brackets / Standings read-only with Recalculate header action).
 *
 * Pitfall 7 / T-06-11-02: the status field is rendered for visibility but
 * `->disabledOn('edit')` so admin cannot flip status via the form. All transitions
 * route through HeaderActions on EditTournament that call TournamentStatusService::transition
 * (server-side state-machine guard + activity_log write).
 *
 * Slug + format are also disabledOn('edit'): slugs are URL keys, format change would
 * invalidate generated brackets.
 *
 * A8 LOCKED inline: admin-only via the panel-level `admin-access` permission (Phase 1
 * canAccessPanel). The plan called for a separate `tournament.manage` permission, but
 * the existing Phase 1-5 idiom gates exclusively at the panel level — keeping that
 * consistent here defers a separate organiser permission tier to v2 (D-012 inherits).
 *
 * Threat refs:
 *   - T-06-11-01 (non-admin access)            — mitigated by canAccessPanel inheritance
 *   - T-06-11-02 (admin bypasses state machine)→ status ->disabledOn('edit'); transitions via HeaderActions only
 *   - T-06-11-04 (admin tampers brackets)       → Brackets+Stages RelationManagers are read-only (no CreateAction/EditAction/DeleteAction)
 */
class TournamentResource extends Resource
{
    protected static ?string $model = Tournament::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('admin.tournament.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.tournament.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.tournament.navigation_group');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('tournament_tabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make(__('admin.tournament.section.profile'))
                        ->icon('heroicon-o-trophy')
                        ->schema([
                            Section::make(__('admin.tournament.section.profile'))
                                ->schema([
                                    Forms\Components\TextInput::make('slug')
                                        ->label(__('admin.tournament.fields.slug'))
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->disabledOn('edit'),

                                    Forms\Components\Select::make('game_id')
                                        ->label(__('admin.tournament.fields.game_id'))
                                        ->relationship('game', 'key')
                                        ->required()
                                        ->searchable()
                                        ->preload(),

                                    // Format is locked on edit — flipping it would invalidate
                                    // any generated brackets/stages.
                                    Forms\Components\Select::make('format')
                                        ->label(__('admin.tournament.fields.format'))
                                        ->options([
                                            'single_elimination' => __('tournaments.formats.single_elimination.label'),
                                            'double_elimination' => __('tournaments.formats.double_elimination.label'),
                                            'round_robin' => __('tournaments.formats.round_robin.label'),
                                            'swiss' => __('tournaments.formats.swiss.label'),
                                        ])
                                        ->required()
                                        ->disabledOn('edit'),

                                    // Pitfall 7 / T-06-11-02: status is shown for visibility,
                                    // but admin cannot flip it via the form. All transitions
                                    // go through HeaderActions in EditTournament.
                                    Forms\Components\Select::make('status')
                                        ->label(__('admin.tournament.fields.status'))
                                        ->options([
                                            'draft' => __('tournaments.status.draft.label'),
                                            'registering' => __('tournaments.status.registering.label'),
                                            'seeded' => __('tournaments.status.seeded.label'),
                                            'running' => __('tournaments.status.running.label'),
                                            'completed' => __('tournaments.status.completed.label'),
                                            'cancelled' => __('tournaments.status.cancelled.label'),
                                        ])
                                        ->default('draft')
                                        ->disabledOn('edit'),

                                    // title is JSONB locale-keyed via HasTranslations.
                                    Forms\Components\KeyValue::make('title')
                                        ->label(__('admin.tournament.fields.title'))
                                        ->reorderable(false)
                                        ->default(['en' => ''])
                                        ->required(),

                                    Forms\Components\KeyValue::make('description')
                                        ->label(__('admin.tournament.fields.description'))
                                        ->reorderable(false)
                                        ->default(['en' => '']),

                                    Forms\Components\DateTimePicker::make('starts_at')
                                        ->label(__('admin.tournament.fields.starts_at'))
                                        ->seconds(false)
                                        ->timezone('UTC')
                                        ->nullable(),

                                    Forms\Components\DateTimePicker::make('ends_at')
                                        ->label(__('admin.tournament.fields.ends_at'))
                                        ->seconds(false)
                                        ->timezone('UTC')
                                        ->nullable(),

                                    Forms\Components\TextInput::make('max_participants')
                                        ->label(__('admin.tournament.fields.max_participants'))
                                        ->numeric()
                                        ->minValue(2)
                                        ->maxValue(64)
                                        ->nullable(),

                                    Forms\Components\Select::make('organiser_user_id')
                                        ->label(__('admin.tournament.fields.organiser_user_id'))
                                        ->relationship('organiser', 'username')
                                        ->required()
                                        ->searchable(),

                                    Forms\Components\Select::make('default_game_match_type_id')
                                        ->label(__('admin.tournament.fields.default_game_match_type_id'))
                                        ->relationship('defaultGameMatchType', 'key')
                                        ->searchable()
                                        ->nullable(),

                                    Forms\Components\Toggle::make('is_public')
                                        ->label(__('admin.tournament.fields.is_public'))
                                        ->default(true),

                                    Forms\Components\KeyValue::make('settings')
                                        ->label(__('admin.tournament.fields.settings'))
                                        ->reorderable(false)
                                        ->keyLabel('Setting')
                                        ->valueLabel('Value')
                                        ->nullable(),
                                ]),
                        ]),

                    Forms\Components\Tabs\Tab::make(__('admin.tournament.section.audit'))
                        ->icon('heroicon-o-archive-box')
                        ->schema([
                            Forms\Components\Placeholder::make('audit_log')
                                ->label('')
                                ->content(fn ($record): View|string => $record !== null
                                    ? view('filament.partials.audit-tab', ['subject' => $record])
                                    : (string) __('admin.audit.no_activity_yet')),
                        ])
                        ->visibleOn('edit'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('admin.tournament.fields.slug'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label(__('admin.tournament.fields.title'))
                    ->getStateUsing(fn ($record): string => is_array($record->title) ? ($record->title['en'] ?? '—') : '—'),

                Tables\Columns\BadgeColumn::make('format')
                    ->label(__('admin.tournament.fields.format'))
                    ->getStateUsing(fn ($record): string => (string) __('tournaments.formats.' . $record->format . '.badge_label'))
                    ->colors([
                        'warning' => fn ($state, $record): bool => $record->format === 'single_elimination',
                        'danger' => fn ($state, $record): bool => $record->format === 'double_elimination',
                        'success' => fn ($state, $record): bool => $record->format === 'round_robin',
                        'primary' => fn ($state, $record): bool => $record->format === 'swiss',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('admin.tournament.fields.status'))
                    ->getStateUsing(fn ($record): string => (string) __('tournaments.status.' . $record->status . '.label'))
                    ->colors([
                        'secondary' => fn ($state, $record): bool => $record->status === 'draft',
                        'info' => fn ($state, $record): bool => $record->status === 'registering',
                        'warning' => fn ($state, $record): bool => $record->status === 'seeded',
                        'success' => fn ($state, $record): bool => $record->status === 'running',
                        'primary' => fn ($state, $record): bool => $record->status === 'completed',
                        'danger' => fn ($state, $record): bool => $record->status === 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('participants_count')
                    ->label(__('admin.tournament.fields.participants_count'))
                    ->counts('participants')
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('admin.tournament.fields.starts_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_public')
                    ->label(__('admin.tournament.fields.is_public'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.tournament.fields.status'))
                    ->options([
                        'draft' => __('tournaments.status.draft.label'),
                        'registering' => __('tournaments.status.registering.label'),
                        'seeded' => __('tournaments.status.seeded.label'),
                        'running' => __('tournaments.status.running.label'),
                        'completed' => __('tournaments.status.completed.label'),
                        'cancelled' => __('tournaments.status.cancelled.label'),
                    ]),

                Tables\Filters\SelectFilter::make('format')
                    ->label(__('admin.tournament.fields.format'))
                    ->options([
                        'single_elimination' => __('tournaments.formats.single_elimination.label'),
                        'double_elimination' => __('tournaments.formats.double_elimination.label'),
                        'round_robin' => __('tournaments.formats.round_robin.label'),
                        'swiss' => __('tournaments.formats.swiss.label'),
                    ]),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label(__('admin.tournament.fields.is_public')),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                // INTENTIONALLY no DeleteAction — tournament cancellation goes via the
                // EditTournament 'cancel' HeaderAction (status → 'cancelled') so the
                // state-machine + audit-log invariants hold.
            ]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            RelationManagers\ParticipantsRelationManager::class,
            RelationManagers\StagesRelationManager::class,
            RelationManagers\BracketsRelationManager::class,
            RelationManagers\StandingsRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTournaments::route('/'),
            'create' => Pages\CreateTournament::route('/create'),
            'edit' => Pages\EditTournament::route('/{record}/edit'),
        ];
    }
}
