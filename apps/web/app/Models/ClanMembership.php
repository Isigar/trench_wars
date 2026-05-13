<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use App\Observers\ClanMembershipObserver;
use Database\Factories\ClanMembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .docs/05-database-schema.md § clan_memberships + D-009.
 *
 * D-009 invariant: at most one active membership (left_at IS NULL) per user.
 * Enforced by partial unique index in migration (WHERE left_at IS NULL).
 * History is preserved — never hard-delete rows; set left_at = now() on leave.
 *
 * Role change routes use a dedicated controller action that validates role (T-02-02-02 mitigation).
 */
class ClanMembership extends Model
{
    /** @use HasFactory<ClanMembershipFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'clan_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
        'invited_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "ClanMembership {$event}");
    }

    /** @return BelongsTo<Clan, $this> */
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Register ClanMembershipObserver for Discord role-sync dispatch (plan 05-06).
     *
     * D-04-08-B precedent (Phase 4 GameMatch::booted()): model-level
     * static::observe is idempotent — Eloquent dedupes by class name, so
     * repeat registrations from AppServiceProvider or repeated booted()
     * invocations are harmless. Registering here (not in AppServiceProvider)
     * is the canonical hook that fires reliably under test.
     */
    protected static function booted(): void
    {
        static::observe(ClanMembershipObserver::class);
    }
}
