<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * Source: .docs/05-database-schema.md § users.
 *
 * P1 user model — Discord-OAuth-only identity (D-002). FilamentUser contract +
 * canAccessPanel are added in plan 12 (this commit). spatie/laravel-permission
 * HasRoles trait was added in plan 11. Plan 14 adds LogsActivity (D-012).
 *
 * No `password` field — Discord OAuth is the only auth path (D-017). Notifiable is
 * retained because spatie/laravel-permission and Filament both call notify() in
 * audit-related flows.
 *
 * `$guard_name = 'web'` pins permission lookups to the same guard Filament uses
 * (research Pitfall 4 mitigation; CLAUDE.md §6).
 */
class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuidPrimaryKey;
    use LogsActivity;
    use Notifiable;

    protected string $guard_name = 'web';

    /** @var list<string> */
    protected $fillable = [
        'discord_id',
        'username',
        'email',
        'avatar_url',
        'locale',
        'last_login_at',
        'left_community_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'left_community_at' => 'datetime',
        ];
    }

    /**
     * Activity log options for User mutations.
     *
     * Source: 01-RESEARCH.md Pattern 3 + plan 14 must_haves —
     * suppress login-spam by ignoring last_login_at-only changes (CLAUDE.md §6
     * "Activity log writes are append-only via the LogsActivity trait").
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['last_login_at'])
            ->setDescriptionForEvent(fn (string $event): string => "User {$event}");
    }

    /**
     * Filament admin-panel access gate.
     *
     * Source: 01-RESEARCH.md Pattern 3 + Pitfall 4 (explicit 'web' guard pin —
     * Spatie permission lookups MUST match Filament's panel guard).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        // A currently-banned admin loses panel access too (ban = no authenticated
        // access anywhere, not just the public web routes).
        if ($this->activeBan() !== null) {
            return false;
        }

        return $this->hasPermissionTo('admin-access', 'web');
    }

    /**
     * Display name shown by Filament chrome (avatar tooltip, header dropdown).
     *
     * Source: Filament getUserName() — falls back to $user->name otherwise, but
     * our schema uses Discord username as canonical display (D-002); no `name`
     * column exists on `users`.
     */
    public function getFilamentName(): string
    {
        return $this->username;
    }

    /**
     * Discord-OAuth-only schema (D-002, D-017) — no `password` column exists on
     * the users table. Laravel's AuthenticateSession middleware nevertheless
     * calls `getAuthPassword()` on the authenticated user before short-circuiting
     * on the falsy-password guard (vendor/.../AuthenticateSession.php L47).
     *
     * Default Authenticatable::getAuthPassword() reads `$this->{password}`,
     * which under Model::shouldBeStrict() (plan 09-08) raises
     * MissingAttributeException at runtime.
     *
     * Override to return an empty string so the middleware's
     * `! $request->user()->getAuthPassword()` guard short-circuits the
     * password-rotation rehash path entirely, without touching strict-mode.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * Resilient to strict-mode (plan 09-08, Model::shouldBeStrict). The framework
     * calls `getRememberToken()` from session middleware + auth logout/cycle
     * flows; if the model was retrieved without the remember_token column in
     * its $attributes (defensive selects, JSON resource projections, manual
     * `select()` chains in test helpers), default Authenticatable accessor
     * would raise MissingAttributeException. Return null instead — the auth
     * stack treats null + empty string identically (no "remember me" cookie).
     */
    public function getRememberToken(): ?string
    {
        $name = $this->getRememberTokenName();
        if ($name === '' || ! array_key_exists($name, $this->getAttributes())) {
            return null;
        }

        $value = $this->getAttributes()[$name] ?? null;

        return $value !== null ? (string) $value : null;
    }

    /**
     * 1:1 relation — every User has exactly one Player (created at first login).
     *
     * @return HasOne<Player, $this>
     */
    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }

    /*
    | -----------------------------------------------------------------------
    | Clan-membership accessors (added in plan 04-03 task 2 — Rule 2 amendment).
    | -----------------------------------------------------------------------
    | Phase 2 ships `Clan::activeMembers()` (HasMany) and `Clan::memberships()`
    | (HasMany), but the symmetric reverse accessors on User were missing.
    | RESEARCH Pattern 5 (`MatchSignupService::tagAccessAllowed()`) reads
    | `$user->activeClanMembership?->clan->tags()` and Phase 5 (Discord bot)
    | + Filament admin both need `$user->memberships()` for history views.
    |
    | D-009 invariant: at most one active ClanMembership per user (enforced by
    | the `clan_memberships_one_active` partial UNIQUE index). The HasOne
    | returns either the single active row or null.
    */

    /** @return HasOne<ClanMembership, $this> */
    public function activeClanMembership(): HasOne
    {
        return $this->hasOne(ClanMembership::class)->whereNull('left_at');
    }

    /** @return HasMany<ClanMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(ClanMembership::class);
    }

    /*
    | -----------------------------------------------------------------------
    | Phase 9 plan 09-03 amendment — notification + ban relations + channel
    | preference resolver (Pattern 7 of 09-RESEARCH.md).
    | -----------------------------------------------------------------------
    | enabledNotificationChannels(string $eventType): array is the single
    | authority on which channels fire for a given user+event pair. Every
    | App\Notifications\* class delegates to it from its `via()` hook.
    |
    | Default policy:
    |   - database bell: default-ON for every event_type.
    |   - discord DM:    default-ON IF user has discord_id AND event_type is
    |                    NOT 'match_result_published' (Open Question 3 LOCKED
    |                    — match-result Discord DMs are opt-in only).
    |
    | Explicit `enabled=false` preference rows always override the default to OFF.
    */

    /** @return HasMany<UserNotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    /** @return HasMany<Ban, $this> */
    public function bans(): HasMany
    {
        return $this->hasMany(Ban::class);
    }

    /**
     * Currently-active ban (lifted_at NULL AND not expired) if any.
     *
     * Consumed by EnsureUserNotBanned (the ban-check middleware on the
     * authenticated web route groups), DiscordController::callback (login gate),
     * and canAccessPanel (Filament gate). Returns the first row matched by
     * Ban::scopeActive() ordered by created_at DESC — deterministic when a user
     * has stacked bans.
     */
    public function activeBan(): ?Ban
    {
        return $this->bans()
            ->active()
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Channel keys the user wants for the given event_type, honouring
     * preference rows + default policy. See class-level docblock for the
     * default rules.
     *
     * Event types with Discord DM default-OFF (Open Question 3 LOCKED).
     *
     * @return array<int, string> e.g. ['database', 'discord'] or ['database']
     */
    public function enabledNotificationChannels(string $eventType): array
    {
        // Use a fresh query rather than the relation accessor — Notification
        // dispatch paths (App\Notifications\*) typically receive a User that
        // was NOT eager-loaded with `notificationPreferences`. Under
        // Model::shouldBeStrict() (plan 09-08) the accessor would raise
        // LazyLoadingViolationException. If the caller pre-loaded the relation
        // we still defer to the in-memory collection (no extra query).
        /** @var iterable<UserNotificationPreference> $rows */
        $rows = $this->relationLoaded('notificationPreferences')
            ? $this->notificationPreferences->where('event_type', $eventType)
            : $this->notificationPreferences()->where('event_type', $eventType)->get();

        /** @var array<string, bool> $prefs */
        $prefs = [];
        foreach ($rows as $row) {
            $prefs[$row->channel] = (bool) $row->enabled;
        }

        $channels = [];

        // database bell — default-ON everywhere.
        if (($prefs['database'] ?? true) === true) {
            $channels[] = 'database';
        }

        // discord DM — default-ON UNLESS the event is match_result_published
        // (Open Question 3 LOCKED: result spam is opt-in). Either way, the
        // user must have a discord_id (the bot DMs to their snowflake).
        if (! empty($this->discord_id)) {
            $discordDefault = $eventType !== 'match_result_published';
            if (($prefs['discord'] ?? $discordDefault) === true) {
                $channels[] = 'discord';
            }
        }

        return $channels;
    }
}
