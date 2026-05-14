<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchServerResource\RelationManagers;

use App\Filament\Resources\MatchResource;
use App\Models\MatchServer;
use App\Models\MatchServerBooking;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Source: .planning/phases/08-rcon-automation/08-09-PLAN.md task 1.
 *
 * Read-only window onto upcoming + recent bookings for a CRCON server. The
 * MatchResource (Phase 4) owns booking creation via its "Book Server" action
 * (out of scope for round 1; surfaced in plan 08-12 if needed).
 *
 * `$relationship` matches {@see MatchServer::bookings()} exactly
 * (Pitfall 3 — Filament v3 RelationManager mounts via the method name).
 *
 * The "view" row action links back to MatchResource::getUrl('edit', ...) so
 * admin can drill from server → booking → match in one click.
 */
class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    public function form(Form $form): Form
    {
        // Read-only — Filament v3 still requires a form() method to satisfy
        // the abstract contract. Booking creation lives on MatchResource.
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('match.scheduled_at')
                    ->label(__('admin.match.fields.scheduled_at'))
                    ->dateTime()
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
            ->actions([
                Tables\Actions\Action::make('view_match')
                    ->label(__('admin.match.label'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (MatchServerBooking $record): string => MatchResource::getUrl(
                        'edit',
                        ['record' => $record->match_id],
                    ))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([])
            ->bulkActions([]);
    }
}
