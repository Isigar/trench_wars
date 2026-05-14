<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .planning/phases/07-cms/07-03-PLAN.md task 1(b).
 *
 * Editorial taxonomy. v1 ships 4 LOCKED categories via CategorySeeder:
 * News, Match Reports, Tournament Updates, Community (Open Question 3 LOCKED).
 *
 * Traits:
 *   - HasUuidPrimaryKey  — UUIDv4 via pgcrypto (Phase 1 idiom)
 *   - HasTranslations on name — JSONB locale-keyed (D-013)
 *   - LogsActivity — D-012 audit trail; useLogName('category') namespaces rows
 *   - SoftDeletes — categories retain history; reverse FK from articles uses
 *     restrictOnDelete so soft-deleting a category in use surfaces as a
 *     QueryException (T-07-03-02 mitigation defence-in-depth)
 *
 * Slug is non-translatable (Open Question 5 LOCKED inline — single-slug v1).
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('category');
    }

    /**
     * Route model binding uses slug (e.g. /news/category/{slug}).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Article, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
