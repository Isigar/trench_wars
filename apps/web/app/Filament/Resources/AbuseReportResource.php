<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AbuseReportResource\Pages;
use App\Models\AbuseReport;
use App\Models\Player;
use App\Services\BanService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Source: .planning/phases/09-polish/09-11-PLAN.md task 2 +
 *         09-RESEARCH.md "Report-abuse flow" +
 *         09-07-PLAN.md MatchDisputeResource template +
 *         CLAUDE.md §6 (activity_log append-only via causedBy/performedOn).
 *
 * AbuseReportResource — admin queue + state-machine transition surface for
 * `abuse_reports`. Permission gates (Pitfall 4 — guard 'web'):
 *
 *   view-reports   — list + view (canViewAny / canView)
 *   manage-reports — dismiss + action_with_ban (table Actions visible())
 *
 * State machine (plan 09-02 + 09-03):
 *   pending → dismissed (moderator: no action)
 *   pending → actioned  (moderator: sanction issued; optional linked Ban via
 *                        action_with_ban path)
 *
 * Both transitions write an activity_log row with
 *   causer=moderator, subject=AbuseReport, log='abuse.report_transitioned'.
 *
 * Threat refs:
 *   - T-09-11-04 (E — Elevation of Privilege) — non-moderator cannot reach
 *     the resource (canViewAny gates the panel slot).
 *   - T-09-11-06 (R — Repudiation) — activity_log row per transition.
 *
 * Linked-ban handoff (action_with_ban):
 *   Only valid when target_type = Player::class (because Bans are issued
 *   against User rows, and Player belongs to User). The Action body resolves
 *   Player→User via `Player::find($report->target_id)->user`. BanService::issue
 *   writes its own user.banned activity_log row, separate from the
 *   abuse.report_transitioned row this Action writes.
 *
 * D-09-03-A: AbuseReport model does NOT use LogsActivity — the audit rows
 * are emitted explicitly inside the table Actions (this file).
 */
class AbuseReportResource extends Resource
{
    protected static ?string $model = AbuseReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('reports.page.title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reports.page.title');
    }

    public static function canViewAny(): bool
    {
        return (bool) Gate::allows('view-reports');
    }

    public static function canView(Model $record): bool
    {
        return (bool) Gate::allows('view-reports');
    }

    public static function canCreate(): bool
    {
        // Reports are submitted via the public POST /reports flow, never from
        // inside the Filament panel.
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        // Status transitions happen through table Actions, not via an edit form.
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        // Hard-delete is forbidden — audit retention requires that abuse
        // reports remain queryable even after action.
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('id')
                ->label('#')
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('reporter.username')
                ->label(__('admin.user.fields.username'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('target_type')
                ->label(__('reports.form.target_type'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('target_id')
                ->label(__('admin.user.fields.username'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('reason_code')
                ->label(__('reports.form.reason_code'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\Textarea::make('body')
                ->label(__('reports.form.body'))
                ->disabled()
                ->dehydrated(false)
                ->rows(6),

            Forms\Components\TextInput::make('status')
                ->label(__('reports.status.pending'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\Textarea::make('review_notes')
                ->label(__('moderation.dispute.fields.resolution_notes'))
                ->disabled()
                ->dehydrated(false)
                ->rows(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reporter.username')
                    ->label(__('admin.user.fields.username'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('target_type')
                    ->label(__('reports.form.target_type'))
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),

                Tables\Columns\TextColumn::make('target_id')
                    ->label('Target ID')
                    ->fontFamily('mono')
                    ->limit(12),

                Tables\Columns\BadgeColumn::make('reason_code')
                    ->label(__('reports.form.reason_code'))
                    // Filament BadgeColumn::colors signature is `colors([$color => $value])`
                    // but multiple reason codes share the same color. Use the closure
                    // form to map each state explicitly without duplicate keys.
                    ->color(fn (string $state): string => match ($state) {
                        'harassment', 'cheating' => 'danger',
                        'spam', 'inappropriate_content' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => __('reports.reason_codes.' . $state)),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('reports.status.pending'))
                    ->colors([
                        'warning' => 'pending',
                        'gray' => 'dismissed',
                        'success' => 'actioned',
                    ])
                    ->formatStateUsing(fn (string $state): string => __('reports.status.' . $state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.user.fields.last_login_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('reports.status.pending'))
                    ->options([
                        'pending' => __('reports.status.pending'),
                        'dismissed' => __('reports.status.dismissed'),
                        'actioned' => __('reports.status.actioned'),
                    ]),

                Tables\Filters\SelectFilter::make('reason_code')
                    ->label(__('reports.form.reason_code'))
                    ->options([
                        'harassment' => __('reports.reason_codes.harassment'),
                        'spam' => __('reports.reason_codes.spam'),
                        'cheating' => __('reports.reason_codes.cheating'),
                        'inappropriate_content' => __('reports.reason_codes.inappropriate_content'),
                        'other' => __('reports.reason_codes.other'),
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),

                Action::make('dismiss')
                    ->label(__('reports.status.dismissed'))
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (AbuseReport $record): bool => $record->status === 'pending'
                        && Gate::allows('manage-reports'))
                    ->form([
                        Forms\Components\Textarea::make('review_notes')
                            ->label(__('moderation.dispute.fields.resolution_notes'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(2000)
                            ->rows(4),
                    ])
                    ->action(function (AbuseReport $record, array $data): void {
                        $by = auth()->user();
                        if ($by === null) {
                            return;
                        }

                        $record->update([
                            'status' => 'dismissed',
                            'reviewed_by_user_id' => $by->getAuthIdentifier(),
                            'reviewed_at' => Carbon::now(),
                            'review_notes' => (string) $data['review_notes'],
                        ]);

                        // Audit row: subject = the report's TARGET (UUID), not
                        // the AbuseReport itself (bigint id incompatible with
                        // activity_log.subject_id uuid schema — plan 01-14
                        // /  09-02 D-09-02-E). The abuse_report_id is captured
                        // in properties so the audit trail still resolves back
                        // to the report row.
                        /** @var class-string<Model> $modelClass */
                        $modelClass = $record->target_type;
                        $target = $modelClass::query()->find($record->target_id);

                        $logger = activity()
                            ->causedBy($by)
                            ->withProperties([
                                'abuse_report_id' => $record->id,
                                'from_status' => 'pending',
                                'to_status' => 'dismissed',
                                'review_notes' => (string) $data['review_notes'],
                                'target_type' => $record->target_type,
                                'target_id' => $record->target_id,
                            ]);

                        if ($target !== null) {
                            $logger = $logger->performedOn($target);
                        }

                        $logger->log('abuse.report_transitioned');

                        Notification::make()
                            ->title(__('reports.status.dismissed'))
                            ->success()
                            ->send();
                    }),

                Action::make('action_with_ban')
                    ->label(__('reports.status.actioned'))
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (AbuseReport $record): bool => $record->status === 'pending'
                        && $record->target_type === Player::class
                        && Gate::allows('manage-reports')
                        && Gate::allows('moderate-users'))
                    ->form([
                        Forms\Components\Textarea::make('review_notes')
                            ->label(__('moderation.dispute.fields.resolution_notes'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(2000)
                            ->rows(4),

                        Forms\Components\Select::make('ban_type')
                            ->label(__('moderation.bulk.ban.ban_type'))
                            ->options([
                                'temporary' => __('moderation.bulk.ban.ban_type_temporary'),
                                'permanent' => __('moderation.bulk.ban.ban_type_permanent'),
                            ])
                            ->required()
                            ->reactive(),

                        Forms\Components\Textarea::make('ban_reason')
                            ->label(__('moderation.bulk.ban.reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(2000)
                            ->rows(3),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('moderation.bulk.ban.expires_at'))
                            ->visible(fn (Forms\Get $get): bool => $get('ban_type') === 'temporary')
                            ->requiredIf('ban_type', 'temporary'),
                    ])
                    ->action(function (AbuseReport $record, array $data, BanService $bans): void {
                        $by = auth()->user();
                        if ($by === null) {
                            return;
                        }

                        try {
                            // Resolve target Player → User.
                            /** @var Player|null $player */
                            $player = Player::query()->find($record->target_id);
                            if ($player === null || $player->user === null) {
                                Notification::make()
                                    ->title(__('moderation.bulk.ban.error_no_target'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $rawExpires = $data['expires_at'] ?? null;
                            $expiresAt = ($rawExpires !== null && $rawExpires !== '')
                                ? Carbon::parse((string) $rawExpires)
                                : null;

                            $bans->issue(
                                user: $player->user,
                                reason: (string) $data['ban_reason'],
                                banType: (string) $data['ban_type'],
                                expiresAt: $expiresAt,
                                issuedBy: $by,
                            );

                            $record->update([
                                'status' => 'actioned',
                                'reviewed_by_user_id' => $by->getAuthIdentifier(),
                                'reviewed_at' => Carbon::now(),
                                'review_notes' => (string) $data['review_notes'],
                            ]);

                            // Audit row: subject = target Player (UUID), not
                            // the AbuseReport (bigint). See dismiss() above
                            // for rationale. abuse_report_id is captured in
                            // properties for the audit trail.
                            activity()
                                ->causedBy($by)
                                ->performedOn($player)
                                ->withProperties([
                                    'abuse_report_id' => $record->id,
                                    'from_status' => 'pending',
                                    'to_status' => 'actioned',
                                    'review_notes' => (string) $data['review_notes'],
                                    'ban_type' => (string) $data['ban_type'],
                                    'target_type' => $record->target_type,
                                    'target_id' => $record->target_id,
                                ])
                                ->log('abuse.report_transitioned');

                            Notification::make()
                                ->title(__('reports.status.actioned'))
                                ->success()
                                ->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    /**
     * Default eager-loads so the table reporter column does not trigger N+1
     * (plan 09-08 strict-mode flip surfaces it otherwise).
     *
     * @return Builder<AbuseReport>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['reporter', 'reviewedBy']);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbuseReports::route('/'),
            'view' => Pages\ViewAbuseReport::route('/{record}'),
        ];
    }
}
