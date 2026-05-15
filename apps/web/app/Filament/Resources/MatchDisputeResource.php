<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Exceptions\InvalidDisputeTransitionException;
use App\Filament\Resources\MatchDisputeResource\Pages;
use App\Models\MatchDispute;
use App\Services\DisputeService;
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
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Source: .planning/phases/09-polish/09-07-PLAN.md task 2 (Wave 5) +
 *         09-RESEARCH.md § Moderator Tooling — Match Disputes.
 *
 * MatchDisputeResource — admin queue + state-machine transition surface for
 * `match_disputes`. Open Question 5 LOCKED (single panel approach): permission
 * gate via `moderate-disputes` on the `web` guard (Pitfall 4) so the resource
 * disappears from the navigation entirely for non-moderators.
 *
 * State machine owned by DisputeService (see service docblock):
 *   open          → under_review
 *   under_review  → resolved | rejected
 *   rejected      → under_review
 *
 * Action::make('transition') invokes DisputeService::transition and surfaces
 * InvalidDisputeTransitionException / InvalidArgumentException as Filament
 * Halt-style error notifications.
 *
 * Pitfall 8 mitigation: required + minLength validations on every form input
 * so Filament shows inline errors rather than silently closing the modal.
 *
 * T-09-07-06 (Information Disclosure — dispute body):
 *   canViewAny() gates via `moderate-disputes` permission; the resource never
 *   surfaces on public routes (single Filament panel, admin-only).
 */
class MatchDisputeResource extends Resource
{
    protected static ?string $model = MatchDispute::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('moderation.dispute.status.open');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.tab.audit'); // fallback reusing existing key until plan 09-12 audits coverage
    }

    public static function canViewAny(): bool
    {
        return (bool) Gate::allows('moderate-disputes');
    }

    public static function canView(Model $record): bool
    {
        return (bool) Gate::allows('moderate-disputes');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Read-only view form (ViewMatchDispute page). Mutations happen via
            // the transition Action only.
            Forms\Components\TextInput::make('match_id')
                ->label(__('admin.match.label'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('raisedBy.username')
                ->label(__('admin.user.fields.username'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\Textarea::make('body')
                ->label(__('moderation.dispute.fields.reason'))
                ->disabled()
                ->dehydrated(false)
                ->rows(6),

            Forms\Components\Select::make('status')
                ->label(__('moderation.dispute.status.open'))
                ->options([
                    'open' => __('moderation.dispute.status.open'),
                    'under_review' => __('moderation.dispute.status.under_review'),
                    'resolved' => __('moderation.dispute.status.resolved'),
                    'rejected' => __('moderation.dispute.status.rejected'),
                ])
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('resolution')
                ->label(__('moderation.dispute.fields.resolution_notes'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\Textarea::make('resolution_notes')
                ->label(__('moderation.dispute.fields.resolution_notes'))
                ->disabled()
                ->dehydrated(false)
                ->rows(4),

            Forms\Components\TextInput::make('resolvedBy.username')
                ->label(__('moderation.dispute.fields.resolved_by'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\DateTimePicker::make('resolved_at')
                ->label(__('moderation.dispute.fields.resolved_at'))
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('match_id')
                    ->label(__('admin.match.label'))
                    ->fontFamily('mono')
                    ->limit(8)
                    ->url(fn (MatchDispute $record): string => "/admin/matches/{$record->match_id}"),

                Tables\Columns\TextColumn::make('raisedBy.username')
                    ->label(__('admin.user.fields.username'))
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('moderation.dispute.status.open'))
                    ->colors([
                        'warning' => 'open',
                        'info' => 'under_review',
                        'success' => 'resolved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => __('moderation.dispute.status.' . $state)),

                Tables\Columns\TextColumn::make('body')
                    ->label(__('moderation.dispute.fields.reason'))
                    ->limit(60),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.user.fields.last_login_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('moderation.dispute.status.open'))
                    ->options([
                        'open' => __('moderation.dispute.status.open'),
                        'under_review' => __('moderation.dispute.status.under_review'),
                        'resolved' => __('moderation.dispute.status.resolved'),
                        'rejected' => __('moderation.dispute.status.rejected'),
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),

                Action::make('transition')
                    ->label(__('moderation.dispute.status.under_review'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    // Hide the Action when the dispute has no legal next states
                    // (status='resolved' is terminal in v1).
                    ->visible(function (MatchDispute $record, DisputeService $svc): bool {
                        return $svc->nextStatesFor($record) !== []
                            && Gate::allows('moderate-disputes');
                    })
                    ->form(function (MatchDispute $record, DisputeService $svc): array {
                        $next = $svc->nextStatesFor($record);
                        $options = [];
                        foreach ($next as $status) {
                            $options[$status] = __('moderation.dispute.status.' . $status);
                        }

                        return [
                            Forms\Components\Select::make('to_status')
                                ->label(__('moderation.dispute.status.under_review'))
                                ->options($options)
                                ->required()
                                ->reactive(),

                            // Resolution Select only appears when the moderator is
                            // moving to 'resolved'. requiredIf() keeps validation
                            // honest at submit time.
                            Forms\Components\Select::make('resolution')
                                ->label(__('moderation.dispute.fields.resolution_notes'))
                                ->options([
                                    'result_amended' => __('moderation.dispute.resolution.result_amended'),
                                    'result_voided' => __('moderation.dispute.resolution.result_voided'),
                                    'no_action' => __('moderation.dispute.resolution.no_action'),
                                    'sanction_issued' => __('moderation.dispute.resolution.sanction_issued'),
                                ])
                                ->visible(fn (Forms\Get $get): bool => $get('to_status') === 'resolved')
                                ->requiredIf('to_status', 'resolved'),

                            Forms\Components\Textarea::make('notes')
                                ->label(__('moderation.dispute.fields.resolution_notes'))
                                ->required()
                                ->minLength(10)
                                ->maxLength(2000)
                                ->rows(4),
                        ];
                    })
                    ->action(function (MatchDispute $record, array $data, DisputeService $svc): void {
                        $by = auth()->user();
                        if ($by === null) {
                            return;
                        }

                        try {
                            $svc->transition(
                                dispute: $record,
                                toStatus: (string) $data['to_status'],
                                resolution: isset($data['resolution']) ? (string) $data['resolution'] : null,
                                notes: isset($data['notes']) ? (string) $data['notes'] : null,
                                by: $by,
                            );

                            Notification::make()
                                ->title(__('moderation.dispute.status.' . $data['to_status']))
                                ->success()
                                ->send();
                        } catch (InvalidDisputeTransitionException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
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
     * Default eager-loads for the table so the resolvedBy / raisedBy columns
     * do not trigger N+1 (plan 09-08 strict-mode flip will surface it otherwise).
     *
     * @return Builder<MatchDispute>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['raisedBy', 'resolvedBy', 'match']);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatchDisputes::route('/'),
            'view' => Pages\ViewMatchDispute::route('/{record}'),
            // INTENTIONALLY no create/edit/delete — disputes are created via
            // the public match dispute form (deferred) and transitioned via
            // the Action above. Hard-delete cascades from match removal only.
        ];
    }
}
