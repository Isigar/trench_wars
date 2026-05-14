<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MatchServerResource\Pages;
use App\Filament\Resources\MatchServerResource\RelationManagers\BookingsRelationManager;
use App\Jobs\Rcon\TestMatchServerConnectionJob;
use App\Models\MatchServer;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Source: .planning/phases/08-rcon-automation/08-09-PLAN.md task 1.
 *
 * MatchServerResource — admin CRUD for the league-owned CRCON registry (D-005,
 * REQ-constraint-league-owns-servers). Resource is gated behind the `manage-rcon`
 * permission (T-08-09-03 mitigation) — non-RCON-admins do NOT see the resource
 * in the Filament nav and the panel returns 403 on direct URL access.
 *
 * `credentials_encrypted` is nested under `credentials_encrypted.api_token` in
 * the form. The MatchServer model casts the whole column to `encrypted:array`,
 * so values at rest are envelope-encrypted under APP_KEY (T-08-03-01 +
 * T-08-09-01 mitigation). The form's `password()->revealable()` flags render the
 * token as `<input type="password">` so it never leaks to the admin's browser
 * via casual screen shoulder-surfing.
 *
 * Test Connection action — table-row Action that dispatches an async Horizon
 * job (T-08-09-02 mitigation: PHP-FPM has a 30s timeout, slow CRCON would hit
 * it). Admin sees an instant "Test queued" notification; the job updates
 * `last_test_*` columns after probing.
 *
 * Bookings relation manager — read-only window onto upcoming + recent bookings.
 * Admin uses the MatchResource (Phase 4) to create bookings via the "Book
 * Server" action (out of scope for round 1; surfaced in plan 08-12 if needed).
 */
class MatchServerResource extends Resource
{
    protected static ?string $model = MatchServer::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';

    /**
     * navigationSort=30 — after Phase 4 EventResource (21) and Phase 5
     * DiscordOutboundMessageResource (22), in its own "RCON" navigation group.
     */
    protected static ?int $navigationSort = 30;

    protected static ?string $navigationGroup = 'RCON';

    public static function getModelLabel(): string
    {
        return __('admin.match_servers.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.match_servers.plural_label');
    }

    /**
     * Gate the entire resource behind `manage-rcon` (T-08-09-03 mitigation).
     *
     * Returning false here both hides the resource from the navigation AND
     * blocks every page (List/Create/Edit) — Filament auto-derives the
     * per-page authorisation checks from this method by default.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('manage-rcon') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin.match_servers.label'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('admin.match_servers.fields.name'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('host')
                        ->label(__('admin.match_servers.fields.host'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('port_rcon')
                        ->label(__('admin.match_servers.fields.port_rcon'))
                        ->required()
                        ->numeric()
                        ->default(8010)
                        ->minValue(1)
                        ->maxValue(65535),

                    Forms\Components\Select::make('region')
                        ->label(__('admin.match_servers.fields.region'))
                        ->options([
                            'eu-central' => 'EU (Central)',
                            'eu-west' => 'EU (West)',
                            'us-east' => 'US (East)',
                            'us-west' => 'US (West)',
                            'ap-southeast' => 'AP (Southeast)',
                        ])
                        ->required(),

                    // T-08-09-01 mitigation: api_token rendered as
                    // <input type="password">; admin clicks the eye icon to
                    // reveal momentarily. ->dehydrateStateUsing wraps the
                    // string into the encrypted:array shape expected by the
                    // model cast.
                    Forms\Components\TextInput::make('credentials_encrypted.api_token')
                        ->label(__('admin.match_servers.fields.password_encrypted'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn (?string $state): ?array => $state !== null && $state !== ''
                            ? ['api_token' => $state]
                            : null),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('admin.match_servers.fields.is_active'))
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.match_servers.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('host')
                    ->label(__('admin.match_servers.fields.host'))
                    ->fontFamily('mono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('port_rcon')
                    ->label(__('admin.match_servers.fields.port_rcon'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('region')
                    ->label(__('admin.match_servers.fields.region'))
                    ->badge(),

                Tables\Columns\TextColumn::make('last_test_status')
                    ->label(__('admin.match_servers.fields.last_test_status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ok' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ok' => __('admin.match_servers.last_test_status.ok'),
                        'error' => __('admin.match_servers.last_test_status.error'),
                        default => __('admin.match_servers.last_test_status.none'),
                    }),

                Tables\Columns\TextColumn::make('last_test_at')
                    ->label(__('admin.match_servers.fields.last_test_at'))
                    ->since()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.match_servers.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.match_servers.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // T-08-09-02 mitigation: async via Horizon — admin sees an
                // instant "queued" notification, the job updates last_test_*
                // columns when CrconHealthProbe returns.
                Tables\Actions\Action::make('test')
                    ->label(__('admin.match_servers.actions.test'))
                    ->icon('heroicon-o-signal')
                    ->color('warning')
                    ->action(function (MatchServer $record): void {
                        TestMatchServerConnectionJob::dispatch($record->id);

                        Notification::make()
                            ->title(__('rcon.audit.test_connection_queued', ['server' => $record->name]))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            BookingsRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatchServers::route('/'),
            'create' => Pages\CreateMatchServer::route('/create'),
            'edit' => Pages\EditMatchServer::route('/{record}/edit'),
        ];
    }
}
