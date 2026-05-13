<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MatchResource\Pages;
use App\Filament\Resources\MatchResource\RelationManagers;
use App\Models\GameMatch;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

/**
 * Source: .planning/phases/04-matches-manual/04-09-PLAN.md task 1.
 *
 * MatchResource — admin CRUD for the GameMatch entity (D-012).
 *
 * NAMING DECISION (D-04-03-A LOCKED): the underlying model class is `App\Models\GameMatch`
 * (class `Match` is a PHP 8.4 parse error). The DB table is `matches` (preserved via
 * Eloquent `protected $table` override on GameMatch); admin routes stay `/admin/matches`.
 *
 * Phase 4 wave 5 surface:
 *   - List page  → /admin/matches
 *   - Create page → /admin/matches/create (3-step HasWizard — see CreateMatch.php)
 *   - View page  → /admin/matches/{record}
 *   - Edit page  → /admin/matches/{record}/edit (status field disabled per Pitfall 7;
 *                  status transitions go via HeaderActions calling MatchStatusService)
 *   - 4 RelationManagers: Slots, AccessRules, Result, Mvps
 *
 * Pitfall 3 (RESEARCH.md): handleRecordCreation lives in CreateMatch.php and wraps
 * GameMatch::create + MatchSlotMaterialiserService::materialise in a SINGLE explicit
 * DB::transaction — Filament v3 does NOT auto-wrap handleRecordCreation, so the
 * explicit transaction is required to prevent orphan GameMatch rows on materialiser
 * throw.
 *
 * Pitfall 7 (RESEARCH.md): status field is `->disabledOn('edit')` — admin cannot flip
 * status via the edit form. Status transitions happen via HeaderActions on EditMatch
 * that call MatchStatusService::transition. Result entry also flips status to 'played'
 * as a service side-effect (MatchResultService::upsert + Pattern 4).
 *
 * Pitfall 2 / Pattern 6 ripple: translatable JSONB fields (title, description) need
 * null-coercion to ['en' => ''] in Create + Edit page mutators.
 *
 * Pitfall 8 / navigationSort: MatchResource sits at 20 (after Phase 3 Game=10,
 * GameMatchType=11) — see EventResource for sort=21 to keep Phase 4 surface contiguous.
 *
 * T-04-09-01: Phase 1 AdminPanelProvider canAccessPanel gate inherited.
 * T-04-09-02: Wizard handleRecordCreation wraps materialiser in DB::transaction (CreateMatch).
 * T-04-09-03: status field ->disabledOn('edit') here in form().
 * T-04-09-07: Audit tab inherits from Phase 1 plan 01-14 partial; GameMatch LogsActivity.
 */
class MatchResource extends Resource
{
    protected static ?string $model = GameMatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('admin.match.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.match.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('match_tabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make(__('admin.match.section.profile'))
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            Section::make(__('admin.match.section.profile'))
                                ->schema([
                                    Forms\Components\Select::make('game_match_type_id')
                                        ->label(__('admin.match.fields.game_match_type'))
                                        ->relationship('gameMatchType', 'key')
                                        ->required()
                                        ->searchable()
                                        ->preload(),

                                    Forms\Components\DateTimePicker::make('scheduled_at')
                                        ->label(__('admin.match.fields.scheduled_at'))
                                        ->seconds(false)
                                        ->timezone('UTC')
                                        ->required(),

                                    Forms\Components\Select::make('organiser_user_id')
                                        ->label(__('admin.match.fields.organiser'))
                                        ->relationship('organiser', 'username')
                                        ->required()
                                        ->searchable(),

                                    Forms\Components\Select::make('host_clan_id')
                                        ->label(__('admin.match.fields.host_clan'))
                                        ->relationship('hostClan', 'slug')
                                        ->searchable()
                                        ->nullable(),

                                    Forms\Components\TextInput::make('server_address')
                                        ->label(__('admin.match.fields.server_address'))
                                        ->maxLength(255)
                                        ->nullable(),

                                    // Pitfall 7: status is rendered for visibility on the edit form
                                    // but ->disabledOn('edit') — admin cannot flip status via the
                                    // form; transitions go through HeaderActions (EditMatch) calling
                                    // MatchStatusService::transition.
                                    Forms\Components\Select::make('status')
                                        ->label(__('admin.match.fields.status'))
                                        ->options([
                                            'draft' => 'Draft',
                                            'open' => 'Open',
                                            'locked' => 'Locked',
                                            'played' => 'Played',
                                            'cancelled' => 'Cancelled',
                                        ])
                                        ->default('open')
                                        ->disabledOn('edit'),

                                    Forms\Components\Toggle::make('is_public')
                                        ->label(__('admin.match.fields.is_public'))
                                        ->default(true),

                                    // title is JSONB locale-keyed via HasTranslations.
                                    // Pitfall 2: ->default(['en' => '']) prevents null submission;
                                    // CreateMatch + EditMatch mutators also coerce.
                                    Forms\Components\KeyValue::make('title')
                                        ->label(__('admin.match.fields.title'))
                                        ->reorderable(false)
                                        ->default(['en' => ''])
                                        ->required(),

                                    Forms\Components\KeyValue::make('description')
                                        ->label(__('admin.match.fields.description'))
                                        ->reorderable(false)
                                        ->default(['en' => '']),
                                ]),
                        ]),

                    Forms\Components\Tabs\Tab::make(__('admin.match.section.audit'))
                        ->icon('heroicon-o-archive-box')
                        ->schema([
                            Forms\Components\Placeholder::make('audit_log')
                                ->label('')
                                ->content(fn ($record): View|string => $record !== null
                                    ? view('filament.partials.audit-tab', ['subject' => $record])
                                    : (string) __('admin.audit.no_activity_yet')),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label(__('admin.match.fields.scheduled_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label(__('admin.match.fields.title'))
                    ->getStateUsing(fn ($record): string => is_array($record->title) ? ($record->title['en'] ?? '—') : '—'),

                Tables\Columns\TextColumn::make('gameMatchType.key')
                    ->label(__('admin.match.fields.game_match_type'))
                    ->fontFamily('mono')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('admin.match.fields.status'))
                    ->colors([
                        'secondary' => 'draft',
                        'success' => 'open',
                        'warning' => 'locked',
                        'info' => 'played',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\IconColumn::make('is_public')
                    ->label(__('admin.match.fields.is_public'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.match.fields.status'))
                    ->options([
                        'draft' => 'Draft',
                        'open' => 'Open',
                        'locked' => 'Locked',
                        'played' => 'Played',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label(__('admin.match.fields.is_public')),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // INTENTIONALLY no DeleteAction — match deletion cascades to slots/result/mvps.
                // Cancellation goes via HeaderActions on the Edit page (status -> 'cancelled').
            ]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            RelationManagers\SlotsRelationManager::class,
            RelationManagers\AccessRulesRelationManager::class,
            RelationManagers\ResultRelationManager::class,
            RelationManagers\MvpsRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatches::route('/'),
            'create' => Pages\CreateMatch::route('/create'),
            'view' => Pages\ViewMatch::route('/{record}'),
            'edit' => Pages\EditMatch::route('/{record}/edit'),
        ];
    }
}
