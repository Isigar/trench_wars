<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\ClanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .docs/05-database-schema.md § clans.
 *
 * `description` is a JSONB locale-keyed column managed by spatie/laravel-translatable.
 * `discord_role_id` is NOT in $fillable for any My-Clan-facing route (T-02-02-01 mitigation).
 */
class Clan extends Model
{
    /** @use HasFactory<ClanFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['description'];

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'tag',
        'name',
        'description',
        'country_code',
        'owner_user_id',
        'status',
        'discord_role_id',
        'discord_announce_channel_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "Clan {$event}");
    }

    /**
     * Route model binding uses slug (e.g. /clans/{slug}).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<ClanTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ClanTag::class, 'clan_clan_tag');
    }

    /** @return HasMany<ClanMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(ClanMembership::class);
    }

    /** @return HasMany<ClanMembership, $this> */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(ClanMembership::class)->whereNull('left_at');
    }
}
