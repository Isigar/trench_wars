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
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null) {
                            return '—';
                        }

                        // Translate the class basename via the
                        // `admin.audit.subject.*` namespace; fall back to the
                        // raw basename when no translation is registered.
                        $basename = class_basename($state);
                        $translated = __('admin.audit.subject.' . $basename);

                        return is_string($translated) && $translated !== 'admin.audit.subject.' . $basename
                            ? $translated
                            : $basename;
                    }),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label(__('admin.audit.col.subject_id'))
                    ->fontFamily('mono')
                    ->limit(8),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('admin.audit.col.description'))
                    ->wrap(),
            ])
            ->filters([
                // WR-06 (01-REVIEW.md): every UI string must flow through __()
                // (D-013). Subject_type values are class strings — they're
                // structural identifiers, but the visible label was leaking
                // class_basename() output un-translated. Now every visible
                // string in this page is i18n-bound.
                SelectFilter::make('event')
                    ->label(__('admin.audit.filter.event'))
                    ->options([
                        'created' => __('admin.audit.event.created'),
                        'updated' => __('admin.audit.event.updated'),
                        'deleted' => __('admin.audit.event.deleted'),
                    ]),
                SelectFilter::make('subject_type')
                    ->label(__('admin.audit.filter.subject_type'))
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn (string $cls): array => [
                            $cls => __('admin.audit.subject.' . class_basename($cls), [], null),
                        ])
                        ->all()),
                Filter::make('date_range')
                    ->label(__('admin.audit.filter.date_range'))
                    ->form([
                        DatePicker::make('from')
                            ->label(__('admin.audit.filter.from')),
                        DatePicker::make('until')
                            ->label(__('admin.audit.filter.until')),
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
