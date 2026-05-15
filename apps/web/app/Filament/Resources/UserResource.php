<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\Ban;
use App\Models\User;
use App\Services\BanService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

/**
 * Source: .planning/phases/01-foundations/01-13-PLAN.md task 1 + CONTEXT.md "P1 Filament resources".
 *
 * Users come via Discord OAuth only (D-002) — no Create page is registered.
 * `discord_id` is read-only and dehydrated(false) so the form never writes it
 * (T-1-28 mitigation; only the OAuth callback in plan 09 mutates it).
 *
 * --- Phase 9 plan 09-07 (Wave 5) extension ---
 *
 * Adds the moderator-facing bulk surface (SC-3):
 *   - ban   BulkAction — issues N Ban rows + N activity_log rows via BanService.
 *   - unban BulkAction — lifts the active ban (if any) on each selected user.
 *
 * Visibility gate: `auth()->user()?->can('moderate-users')` on every BulkAction
 * (T-09-07-01 — elevation-gate; defence in depth — the panel already requires
 * `admin-access`). Open Question 5 LOCKED: single panel approach, per-resource
 * permission gates.
 *
 * Pitfall 8 mitigation: every BulkAction form field has `->required()` +
 * `->minLength(10)` (where applicable) so Filament shows inline validation
 * errors instead of silently closing the modal. expires_at uses `->required()`
 * conditional on ban_type='temporary' via `->requiredIf()`.
 *
 * Also mounts BansRelationManager (read-only ban history on the User edit page).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('admin.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.user.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('user_tabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make(__('admin.tab.profile'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\TextInput::make('discord_id')
                                ->label(__('admin.user.fields.discord_id'))
                                ->disabled()
                                ->dehydrated(false),
                            Forms\Components\TextInput::make('username')
                                ->label(__('admin.user.fields.username'))
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('email')
                                ->label(__('admin.user.fields.email'))
                                ->email()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('avatar_url')
                                ->label(__('admin.user.fields.avatar_url'))
                                ->url()
                                ->maxLength(2048),
                            Forms\Components\Select::make('locale')
                                ->label(__('admin.user.fields.locale'))
                                ->options(self::localeOptions())
                                ->default('en')
                                ->required(),
                            Forms\Components\DateTimePicker::make('last_login_at')
                                ->label(__('admin.user.fields.last_login_at'))
                                ->disabled()
                                ->dehydrated(false),
                        ]),

                    Forms\Components\Tabs\Tab::make(__('admin.tab.audit'))
                        ->icon('heroicon-o-archive-box')
                        ->schema([
                            Forms\Components\Placeholder::make('audit_log')
                                ->label('')
                                ->content(fn ($record): View => view('filament.partials.audit-tab', [
                                    'subject' => $record,
                                ])),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('discord_id')
                    ->label(__('admin.user.fields.discord_id'))
                    ->copyable()
                    ->searchable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('username')
                    ->label(__('admin.user.fields.username'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('admin.user.fields.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('admin.user.fields.last_login_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('last_login_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Phase 9 plan 09-07 (Wave 5) — ban / unban bulk actions.
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('ban')
                        ->label(__('moderation.bulk.ban.label'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (EloquentCollection $records): string => __('moderation.bulk.ban.modal_heading', [
                            'count' => $records->count(),
                        ]))
                        ->modalDescription(__('moderation.bulk.ban.modal_description'))
                        ->modalSubmitActionLabel(__('moderation.bulk.ban.confirm'))
                        ->form([
                            Forms\Components\Select::make('ban_type')
                                ->label(__('moderation.bulk.ban.ban_type'))
                                ->options([
                                    'temporary' => __('moderation.ban.types.temporary'),
                                    'permanent' => __('moderation.ban.types.permanent'),
                                ])
                                ->default('temporary')
                                ->required()
                                ->reactive(),
                            // Pitfall 8: ->required() + ->minLength(10) so Filament
                            // renders inline validation rather than silently closing.
                            Forms\Components\Textarea::make('reason')
                                ->label(__('moderation.bulk.ban.reason'))
                                ->required()
                                ->minLength(10)
                                ->maxLength(500),
                            // expires_at is required ONLY when ban_type=temporary
                            // (permanent bans force expires_at=null at the service).
                            Forms\Components\DateTimePicker::make('expires_at')
                                ->label(__('moderation.bulk.ban.expires_at'))
                                ->seconds(false)
                                ->after('now')
                                ->visible(fn (Forms\Get $get): bool => $get('ban_type') === 'temporary')
                                ->requiredIf('ban_type', 'temporary'),
                        ])
                        ->action(function (EloquentCollection $records, array $data, BanService $bans): void {
                            $issuer = auth()->user();
                            if ($issuer === null) {
                                return;
                            }

                            // Filament casts unset DateTimePicker values to null/'' both,
                            // so `!empty()` guards both shapes. PHPStan complains about
                            // a redundant `!== null` after `isset()` (mixed is already
                            // non-null inside the isset branch).
                            $rawExpiresAt = $data['expires_at'] ?? null;
                            $expiresAt = ($rawExpiresAt !== null && $rawExpiresAt !== '')
                                ? Carbon::parse((string) $rawExpiresAt)
                                : null;

                            /** @var User $user */
                            foreach ($records as $user) {
                                $bans->issue(
                                    user: $user,
                                    reason: (string) $data['reason'],
                                    banType: (string) $data['ban_type'],
                                    expiresAt: $expiresAt,
                                    issuedBy: $issuer,
                                );
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => (bool) auth()->user()?->can('moderate-users')),

                    Tables\Actions\BulkAction::make('unban')
                        ->label(__('moderation.bulk.unban.label'))
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (EloquentCollection $records): string => __('moderation.bulk.unban.modal_heading', [
                            'count' => $records->count(),
                        ]))
                        ->modalDescription(__('moderation.bulk.unban.modal_description'))
                        ->modalSubmitActionLabel(__('moderation.bulk.unban.confirm'))
                        ->form([
                            Forms\Components\Textarea::make('lift_reason')
                                ->label(__('moderation.bulk.unban.modal_description'))
                                ->required()
                                ->minLength(10)
                                ->maxLength(500),
                        ])
                        ->action(function (EloquentCollection $records, array $data, BanService $bans): void {
                            $lifter = auth()->user();
                            if ($lifter === null) {
                                return;
                            }

                            /** @var User $user */
                            foreach ($records as $user) {
                                // Lift every currently-active ban on this user.
                                // active() scope: lifted_at IS NULL AND
                                // (expires_at IS NULL OR expires_at > now()).
                                $activeBans = Ban::query()
                                    ->where('user_id', $user->id)
                                    ->active()
                                    ->get();

                                foreach ($activeBans as $ban) {
                                    $bans->lift($ban, $lifter, (string) $data['lift_reason']);
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => (bool) auth()->user()?->can('moderate-users')),
                ]),
            ]);
    }

    /**
     * Locale codes the panel can offer for User->locale, sourced from config/i18n.php.
     *
     * @return array<string, string>
     */
    private static function localeOptions(): array
    {
        /** @var array<int, string> $locales */
        $locales = config('i18n.available_locales', ['en']);
        $options = [];
        foreach ($locales as $code) {
            $options[$code] = $code;
        }

        return $options;
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            RelationManagers\BansRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            // INTENTIONALLY no 'create' route — users come via Discord OAuth (D-002).
        ];
    }
}
