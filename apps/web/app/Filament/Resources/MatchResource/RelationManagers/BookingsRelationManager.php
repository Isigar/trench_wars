<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\RelationManagers;

use App\Models\GameMatch;
use App\Models\MatchServer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Booking creation surface on the Match edit page — the (previously missing)
 * entry point that makes the automatic CRCON capture pipeline reachable.
 *
 * Nothing in the app created a match_server_bookings row, so REQ-goal-rcon-
 * history (✓ v1.0) could never fire: the rcon-worker polls due bookings
 * (BookingScheduleController::dueNow), but no booking ever existed to poll. The
 * read-only window on MatchServerResource already pointed here ("MatchResource
 * owns booking creation"); this relation manager fills that promise.
 *
 * Reserves a CRCON server for the match's play window. The Postgres EXCLUDE
 * constraint `match_server_bookings_no_overlap` is the canonical guard against
 * double-booking an active server — a 23P01 exclusion_violation is caught and
 * surfaced as a friendly notification instead of a 500.
 *
 * Pitfall 3: $relationship MUST match GameMatch::bookings() HasMany method.
 */
class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.match_server_bookings.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('server_id')
                ->label(__('admin.match_server_bookings.fields.server_id'))
                ->options(fn (): array => MatchServer::query()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required(),

            Forms\Components\DateTimePicker::make('reserved_from')
                ->label(__('admin.match_server_bookings.fields.reserved_from'))
                // Default the reservation window to the match's scheduled time so
                // the worker's ±5min dueNow window lines up with kickoff.
                ->default(function (RelationManager $livewire): ?string {
                    $owner = $livewire->getOwnerRecord();

                    return $owner instanceof GameMatch ? (string) $owner->scheduled_at : null;
                })
                ->seconds(false)
                ->required(),

            Forms\Components\DateTimePicker::make('reserved_to')
                ->label(__('admin.match_server_bookings.fields.reserved_to'))
                ->seconds(false)
                ->after('reserved_from')
                ->required(),

            Forms\Components\Select::make('status')
                ->label(__('admin.match_server_bookings.fields.status'))
                ->options([
                    'active' => 'active',
                    'cancelled' => 'cancelled',
                    'completed' => 'completed',
                ])
                ->default('active')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('server.name')
                    ->label(__('admin.match_server_bookings.fields.server_id'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('reserved_from')
                    ->label(__('admin.match_server_bookings.fields.reserved_from'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reserved_to')
                    ->label(__('admin.match_server_bookings.fields.reserved_to'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.match_server_bookings.fields.status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('reserved_from', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    // The EXCLUDE constraint is the canonical no-overlap arbiter
                    // (never preflight via SELECT — TOCTOU). The INSERT runs in a
                    // savepoint (DB::transaction nests when already in a tx) so a
                    // 23P01 exclusion_violation rolls back cleanly — without it the
                    // failed INSERT poisons the surrounding transaction (25P02) and
                    // every later query in the request errors. Caught + surfaced as
                    // a notification + Halt instead of a 500.
                    ->using(function (array $data, RelationManager $livewire): Model {
                        try {
                            /** @var Model $record */
                            $record = DB::transaction(
                                fn (): Model => $livewire->getRelationship()->create($data),
                            );

                            return $record;
                        } catch (QueryException $e) {
                            if ($e->getCode() === '23P01') {
                                Notification::make()
                                    ->title(__('admin.match_server_bookings.overlap'))
                                    ->danger()
                                    ->send();

                                throw new Halt;
                            }

                            throw $e;
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
