<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MatchDisputeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 1 +
 *         09-02 migration 2026_05_18_100300 (table match_disputes) +
 *         09-OPEN-QUESTIONS A9 LOCKED (cascadeOnDelete on match_id).
 *
 * Per-user-per-match dispute row. State machine (plan 09-07 DisputeService):
 *   open → resolved | dismissed | withdrawn
 *
 * resolution enum (only meaningful when status='resolved'; application-validated):
 *   result_amended | result_voided | no_action | sanction_issued
 *
 * Pitfall 11: partial UNIQUE `one_open_dispute_per_user_per_match` enforces at most
 * one open dispute per (match_id, raised_by_user_id).
 *
 * D-04-03-A LOCKED — the owner model is `App\Models\GameMatch` (NOT `Match`,
 * which is a reserved PHP 8 keyword). The BelongsTo explicitly passes the
 * `match_id` FK because column naming derives from the DB schema, not the class.
 *
 * NOTE — D-09-03-A: NO LogsActivity (see Ban.php docblock — services emit hand-
 * rolled activity rows in plan 09-07 DisputeService).
 */
final class MatchDispute extends Model
{
    /** @use HasFactory<MatchDisputeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'match_id',
        'raised_by_user_id',
        'body',
        'status',
        'resolution',
        'resolution_notes',
        'resolved_by_user_id',
        'resolved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GameMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /** @return BelongsTo<User, $this> */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
