<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\ClanTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .docs/05-database-schema.md § clan_tags.
 *
 * `label` is a JSONB locale-keyed column managed by spatie/laravel-translatable (Pitfall 5 in RESEARCH.md).
 */
class ClanTag extends Model
{
    /** @use HasFactory<ClanTagFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    public array $translatable = ['label'];

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'label',
        'color',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "ClanTag {$event}");
    }

    /** @return BelongsToMany<Clan, $this> */
    public function clans(): BelongsToMany
    {
        return $this->belongsToMany(Clan::class, 'clan_clan_tag');
    }
}
