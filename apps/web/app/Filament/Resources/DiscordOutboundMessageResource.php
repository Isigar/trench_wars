<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DiscordOutboundMessageResource\Pages;
use App\Models\DiscordOutboundMessage;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Source: .planning/phases/05-discord-bot-v1/05-07-PLAN.md task 1
 * + 05-RESEARCH.md Q3 retry semantics + CONTEXT.md Filament additions.
 *
 * DiscordOutboundMessageResource — READ-ONLY admin surface for the Phase 5 outbox
 * (List + View only; no Create/Edit/Delete pages). Admin can:
 *   - browse all outbound rows the observer + role-sync job have written
 *   - filter by status (pending/dispatching/sent/failed) + message_type
 *   - retry a failed row via the bespoke `retry` table action — flips
 *     status=failed → pending, zeros attempts, clears last_error + backoff_until.
 *
 * T-05-07-01 mitigation: no Create/Edit pages — admin cannot inject arbitrary
 * payloads or status transitions. Only the retry action is exposed, and its
 * closure is server-side (admin cannot pick an arbitrary target state).
 *
 * T-05-07-05 mitigation: retry writes an activity_log row with the admin causer
 * (D-012). The Model already has the LogsActivity trait so DB-side mutations
 * generate the standard `updated` event; the retry action additionally emits
 * a custom `retry` event for filtering in the audit log.
 *
 * navigationSort=22 (Phase 4 EventResource is 21).
 */
class DiscordOutboundMessageResource extends Resource
{
    protected static ?string $model = DiscordOutboundMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?int $navigationSort = 22;

    protected static ?string $navigationGroup = 'Discord';

    public static function getModelLabel(): string
    {
        return __('admin.discord_outbound_message.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.discord_outbound_message.plural_label');
    }

    public static function form(Form $form): Form
    {
        // View-only — Filament still requires form() but no fields are editable.
        return $form->schema([]);
    }

    /**
     * Read-only View-page infolist. Without this, Filament falls back to the
     * empty form() above and the View page renders blank.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns(2)
            ->schema([
                Components\TextEntry::make('message_type')
                    ->label(__('admin.discord_outbound_message.fields.message_type'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'match_announce' => 'info',
                        'role_sync' => 'warning',
                        default => 'gray',
                    }),

                Components\TextEntry::make('status')
                    ->label(__('admin.discord_outbound_message.fields.status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'dispatching' => 'info',
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Components\TextEntry::make('channel_id')
                    ->label(__('admin.discord_outbound_message.fields.channel_id'))
                    ->fontFamily('mono')
                    ->copyable(),

                Components\TextEntry::make('sent_message_id')
                    ->label(__('admin.discord_outbound_message.fields.sent_message_id'))
                    ->fontFamily('mono')
                    ->placeholder('—'),

                Components\TextEntry::make('attempts')
                    ->label(__('admin.discord_outbound_message.fields.attempts'))
                    ->numeric(),

                Components\TextEntry::make('backoff_until')
                    ->label(__('admin.discord_outbound_message.fields.backoff_until'))
                    ->dateTime()
                    ->placeholder('—'),

                Components\TextEntry::make('causer.username')
                    ->label(__('admin.discord_outbound_message.fields.causer'))
                    ->placeholder('system'),

                Components\TextEntry::make('created_at')
                    ->label(__('admin.discord_outbound_message.fields.created_at'))
                    ->dateTime(),

                Components\TextEntry::make('last_error')
                    ->label(__('admin.discord_outbound_message.fields.last_error'))
                    ->placeholder('—')
                    ->columnSpanFull(),

                Components\TextEntry::make('payload')
                    ->label(__('admin.discord_outbound_message.fields.payload'))
                    ->fontFamily('mono')
                    ->columnSpanFull()
                    ->getStateUsing(fn (DiscordOutboundMessage $record): string => json_encode(
                        $record->payload,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    ) ?: '—'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.discord_outbound_message.fields.created_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('message_type')
                    ->label(__('admin.discord_outbound_message.fields.message_type'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'match_announce' => 'info',
                        'role_sync' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.discord_outbound_message.fields.status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'dispatching' => 'info',
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('channel_id')
                    ->label(__('admin.discord_outbound_message.fields.channel_id'))
                    ->fontFamily('mono')
                    ->limit(20),

                Tables\Columns\TextColumn::make('attempts')
                    ->label(__('admin.discord_outbound_message.fields.attempts'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_error')
                    ->label(__('admin.discord_outbound_message.fields.last_error'))
                    ->limit(60)
                    ->tooltip(fn (DiscordOutboundMessage $record): ?string => $record->last_error),

                Tables\Columns\TextColumn::make('sent_message_id')
                    ->label(__('admin.discord_outbound_message.fields.sent_message_id'))
                    ->fontFamily('mono')
                    ->limit(15)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('causer.username')
                    ->label(__('admin.discord_outbound_message.fields.causer'))
                    ->placeholder('system'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.discord_outbound_message.fields.status'))
                    ->options([
                        'pending' => __('admin.discord_outbound_message.status.pending'),
                        'dispatching' => __('admin.discord_outbound_message.status.dispatching'),
                        'sent' => __('admin.discord_outbound_message.status.sent'),
                        'failed' => __('admin.discord_outbound_message.status.failed'),
                    ]),

                Tables\Filters\SelectFilter::make('message_type')
                    ->label(__('admin.discord_outbound_message.fields.message_type'))
                    ->options([
                        'match_announce' => 'Match announce',
                        'role_sync' => 'Role sync',
                        'generic' => 'Generic',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // T-05-07-05 audit: every retry writes an activity_log entry with
                // the admin causer (auth()->user()) for repudiation defence.
                Tables\Actions\Action::make('retry')
                    ->label(__('admin.discord_outbound_message.actions.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (DiscordOutboundMessage $record): bool => $record->status === 'failed')
                    ->action(function (DiscordOutboundMessage $record): void {
                        $record->update([
                            'status' => 'pending',
                            'attempts' => 0,
                            'last_error' => null,
                            'backoff_until' => null,
                        ]);

                        activity()
                            ->causedBy(auth()->user())
                            ->performedOn($record)
                            ->event('retry')
                            ->log('admin re-queued failed outbound message');

                        Notification::make()
                            ->title(__('admin.discord_outbound_message.actions.retry_success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]); // INTENTIONALLY no bulk actions — append-only audit semantics.
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            // INTENTIONALLY omits 'create' and 'edit' — read-only resource (T-05-07-01).
            'index' => Pages\ListDiscordOutboundMessages::route('/'),
            'view' => Pages\ViewDiscordOutboundMessage::route('/{record}'),
        ];
    }
}
