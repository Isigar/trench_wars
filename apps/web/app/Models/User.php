<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
     * 1:1 relation — every User has exactly one Player (created at first login).
     *
     * @return HasOne<Player, $this>
     */
    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }
}
