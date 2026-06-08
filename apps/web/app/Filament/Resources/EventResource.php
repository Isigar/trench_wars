<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use App\Models\GameMatch;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Source: .planning/phases/04-matches-manual/04-09-PLAN.md task 2.
 *
 * EventResource — READ-ONLY surface (List + View only) for the polymorphic `events`
 * projection that MatchObserver (plan 04-08) maintains.
 *
 * T-04-09-06 mitigation: NO Create / Edit / Delete pages — the observer owns the
 * events table. Manual edits would drift from the matches.is_public/status invariants
 * (MatchObserver::saved() unconditionally overwrites starts_at, title, is_public on
 * every save of the parent GameMatch).
 *
 * navigationSort=21 places EventResource immediately after MatchResource=20
 * (Pitfall 8 / Phase 3 precedent: Game=10, GameMatchType=11).
 *
 * Phase 7 calendar page (plan 04-10) consumes the same Event table on the public
 * side; this admin resource is the back-office review surface.
 */
class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 21;

    public static function getModelLabel(): string
    {
        return __('admin.event.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.event.plural_label');
    }

    /**
     * Read-only View-page infolist. Without this, Filament falls back to the
     * (intentionally empty) form and the View page renders blank.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns(2)
            ->schema([
                Components\TextEntry::make('title')
                    ->label(__('admin.event.fields.title'))
                    ->getStateUsing(function (Event $record): string {
                        $translations = $record->getTranslations('title');

                        return $translations['en'] ?? (array_values($translations)[0] ?? '—');
                    }),

                Components\TextEntry::make('eventable_type')
                    ->label(__('admin.event.fields.eventable'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        GameMatch::class => __('admin.match.label'),
                        default => $state !== null ? class_basename($state) : '—',
                    }),

                Components\TextEntry::make('starts_at')
                    ->label(__('admin.event.fields.starts_at'))
                    ->dateTime(),

                Components\TextEntry::make('ends_at')
                    ->label(__('admin.event.fields.ends_at'))
                    ->dateTime()
                    ->placeholder('—'),

                Components\IconEntry::make('is_public')
                    ->label(__('admin.event.fields.is_public'))
                    ->boolean(),

                Components\TextEntry::make('created_at')
                    ->label(__('admin.event.fields.created_at'))
                    ->dateTime(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('admin.event.fields.starts_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('eventable_type')
                    ->label(__('admin.event.fields.eventable'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        GameMatch::class => __('admin.match.label'),
                        default => $state !== null ? class_basename($state) : '—',
                    })
                    ->colors([
                        'success' => GameMatch::class,
                    ]),

                Tables\Columns\TextColumn::make('title')
                    ->label(__('admin.event.fields.title'))
                    ->getStateUsing(fn ($record): string => is_array($record->title) ? ($record->title['en'] ?? '—') : '—'),

                Tables\Columns\IconColumn::make('is_public')
                    ->label(__('admin.event.fields.is_public'))
                    ->boolean(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label(__('admin.event.fields.is_public')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // INTENTIONALLY no EditAction / DeleteAction — observer-managed (T-04-09-06).
            ]);
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
            // INTENTIONALLY omits 'create' and 'edit' — read-only resource (T-04-09-06).
            'index' => Pages\ListEvents::route('/'),
            'view' => Pages\ViewEvent::route('/{record}'),
        ];
    }
}
