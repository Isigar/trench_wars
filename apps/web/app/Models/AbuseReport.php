<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AbuseReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 1 +
 *         09-02 migration 2026_05_18_100400 (table abuse_reports).
 *
 * User-submitted report against any reportable entity. Morph-style target
 * columns admit BOTH UUID PK targets (Clan, Player, Article, GameMatch) AND any
 * future bigint PK targets — `target_id` is stored as VARCHAR (D-09-02-E) and
 * the application validates the (target_type, target_id) pair per reason_code
 * in plan 09-11.
 *
 * reason_code enum (application-validated; plan 09-11 FormRequest):
 *   harassment | spam | cheating | inappropriate_content | other
 *
 * status state machine (plan 09-11 service):
 *   pending → dismissed (no action) | actioned (sanction/content removed)
 *
 * NOTE — D-09-03-A: NO LogsActivity (services emit hand-rolled activity rows in
 * plan 09-11 AbuseReportService).
 */
final class AbuseReport extends Model
{
    /** @use HasFactory<AbuseReportFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'reporter_user_id',
        'target_type',
        'target_id',
        'reason_code',
        'body',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * Polymorphic target — target_type is the FQN and target_id is the row id
     * (stored as varchar so both UUID and bigint PKs are admissible — D-09-02-E).
     *
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
