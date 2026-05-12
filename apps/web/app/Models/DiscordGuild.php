<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\DiscordGuildFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .docs/05-database-schema.md § discord_guild.
 *
 * D-003: holds exactly one row for the league's Discord guild.
 * Seeder populates the stub row; admin fills guild_id + channel IDs after bot setup.
 * Filament DiscordGuildResource exposes Edit only (no Create) to enforce the single-row invariant.
 */
class DiscordGuild extends Model
{
    /** @use HasFactory<DiscordGuildFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /**
     * Eloquent default would be 'discord_guilds'; override to singular per schema (D-003).
     */
    protected $table = 'discord_guild';

    /** @var list<string> */
    protected $fillable = [
        'guild_id',
        'name',
        'icon_url',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "DiscordGuild {$event}");
    }
}
