<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 1 +
 *         09-02 migration 2026_05_18_100200 (table bans) +
 *         09-OPEN-QUESTIONS Q4 LOCKED (site-wide bans v1; no clan_id).
 *
 * Site-wide ban row issued by a moderator/admin. "Active" criteria:
 *   lifted_at IS NULL AND (expires_at IS NULL OR expires_at > now())
 *
 * ban_type enum (application-validated; plan 09-07 BanService Pest):
 *   temporary | permanent
 *
 * NOTE — D-09-03-A: this model intentionally does NOT use `LogsActivity`.
 * Per plan 09-03 Anti-Patterns ("DON'T add LogsActivity to bans/disputes/
 * abuse_reports — services call activity()->log() explicitly"), the moderator
 * service layer (plan 09-07 BanService) emits a hand-rolled activity_log row
 * per transition so the activity description is human-readable
 * (`User <name> banned by <moderator>: <reason>`) rather than the trait's
 * auto-generated `Ban created/updated` skeleton.
 */
final class Ban extends Model
{
    /** @use HasFactory<BanFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'ban_type',
        'reason',
        'expires_at',
        'issued_by_user_id',
        'lifted_at',
        'lifted_by_user_id',
        'lift_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by_user_id');
    }

    /**
     * Active = not yet lifted AND not yet expired.
     *
     * @param  Builder<Ban>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('lifted_at')
            ->where(function (Builder $sub): void {
                $sub->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
