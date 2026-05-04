<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Source: 01-CONTEXT.md "Audit infrastructure" + 01-RESEARCH.md Open Question #3
 * (build vanilla Filament page over Activity::query(); skip Filament-specific
 * activitylog plugins to avoid v3/v4 + activitylog v5 compat dependency-chain risk).
 *
 * Read-only by design (CLAUDE.md \xC2\xA76 + D-012 LOCKED). No edit/delete actions on
 * activity_log rows are exposed here. Access is gated by the `audit.view`
 * permission seeded in PermissionSeeder (plan 11).
 */
class Audit extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.audit';

    protected static ?string $slug = 'audit';

    public static function getNavigationLabel(): string
    {
        return __('admin.audit.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.audit.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.audit.col.created_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.username')
                    ->label(__('admin.audit.col.causer')),
                Tables\Columns\TextColumn::make('event')
                    ->label(__('admin.audit.col.event'))
                    ->badge(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label(__('admin.audit.col.subject_type'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : class_basename($state)),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label(__('admin.audit.col.subject_id'))
                    ->fontFamily('mono')
                    ->limit(8),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('admin.audit.col.description'))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'created' => 'created',
                        'updated' => 'updated',
                        'deleted' => 'deleted',
                    ]),
                SelectFilter::make('subject_type')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn (string $cls): array => [$cls => class_basename($cls)])
                        ->all()),
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['from'] ?? null, fn (Builder $q, string $from): Builder => $q->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, string $until): Builder => $q->whereDate('created_at', '<=', $until))),
            ]);
    }

    /**
     * Default record key — Activity uses an int PK, satisfy InteractsWithTable.
     */
    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getKey();
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->hasPermissionTo('audit.view', 'web');
    }
}
