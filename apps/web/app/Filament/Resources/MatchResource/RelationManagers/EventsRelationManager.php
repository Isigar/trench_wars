<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\RelationManagers;

use App\Models\GameMatch;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only window onto a match's normalised CRCON event stream.
 *
 * MatchEvent rows are the append-only per-match event log feeding stat
 * aggregation + result inference, but they were exposed nowhere in admin. When
 * auto-capture confidence is low (CloseMatchJob sets manual_entry_required), an
 * admin curating the result had no way to inspect the underlying event timeline
 * that drove the inference. This relation manager surfaces it.
 *
 * STRICTLY READ-ONLY: match events are immutable by design (append-only,
 * composite-unique on crcon_stream_id). No create/edit/delete actions.
 *
 * Pitfall 3: $relationship MUST match GameMatch::matchEvents() HasMany method.
 */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'matchEvents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.match_events.plural_label');
    }

    public function form(Form $form): Form
    {
        // Read-only — Filament v3 still requires a form() method to satisfy the
        // abstract contract. Events are never authored from the admin UI.
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label(__('admin.match_events.fields.occurred_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label(__('admin.match_events.fields.event_type'))
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('crcon_action')
                    ->label(__('admin.match_events.fields.crcon_action'))
                    ->placeholder('—'),
            ])
            ->defaultSort('occurred_at', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label(__('admin.match_events.fields.event_type'))
                    ->options(function (): array {
                        $owner = $this->getOwnerRecord();

                        return $owner instanceof GameMatch
                            ? $owner->matchEvents()->distinct()->pluck('event_type', 'event_type')->all()
                            : [];
                    }),
            ])
            // Append-only + immutable — no create/edit/delete actions.
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
