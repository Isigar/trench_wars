<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Source: .docs/05-database-schema.md § users.
 *
 * P1 user model — Discord-OAuth-only identity (D-002). FilamentUser contract +
 * canAccessPanel + spatie/laravel-permission HasRoles trait are added in plan 11.
 *
 * No `password` field — Discord OAuth is the only auth path (D-017). Notifiable is
 * retained because spatie/laravel-permission and Filament both call notify() in
 * audit-related flows.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use Notifiable;

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
     * 1:1 relation — every User has exactly one Player (created at first login).
     *
     * @return HasOne<Player, $this>
     */
    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }
}
