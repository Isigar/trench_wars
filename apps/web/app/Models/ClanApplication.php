<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\ClanApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .docs/05-database-schema.md § clan_applications.
 *
 * State machine: pending → accepted | declined | cancelled.
 * State transitions go through ClanApplicationService (plan 02-09+).
 */
class ClanApplication extends Model
{
    /** @use HasFactory<ClanApplicationFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'clan_id',
        'applicant_user_id',
        'status',
        'message',
        'decided_at',
        'decided_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "ClanApplication {$event}");
    }

    /** @return BelongsTo<Clan, $this> */
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
