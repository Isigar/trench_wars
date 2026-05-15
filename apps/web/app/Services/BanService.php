<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ban;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Source: .planning/phases/09-polish/09-07-PLAN.md task 1 +
 *         09-RESEARCH.md § Moderator Tooling — Site-wide bans +
 *         CLAUDE.md §6 (activity_log writes are append-only via causedBy/performedOn).
 *
 * BanService — the canonical mutation surface for the `bans` table.
 *
 * Every moderator-facing surface (Filament UserResource bulk ban + unban
 * BulkActions in plan 09-07 task 2, future per-user single-action ban Action,
 * future AbuseReportResource → ban hand-off in plan 09-11) writes through
 * this service so that:
 *
 *   1. ban_type validity (`temporary` | `permanent`) is enforced application-side
 *      (the migration LEFT the column as a free string per CLAUDE.md §2.D-021
 *      portability decision — DB CHECK would have hard-coded the enum).
 *   2. expires_at consistency is enforced (permanent → expires_at MUST be null;
 *      temporary → expires_at REQUIRED).
 *   3. An activity_log row is written with causer=issuer, subject=banned user,
 *      properties=[ban_type, reason, expires_at(ISO8601)] — Spatie activitylog
 *      Pattern, mirrors MatchStatusService L74-78.
 *   4. lift() is symmetric — flips lifted_at + lifted_by + lift_reason and
 *      writes a paired activity_log row (log='user.ban_lifted') for the audit
 *      trail.
 *
 * D-09-03-A LOCKED (plan 09-03 Ban.php docblock): NO LogsActivity trait on the
 * Ban model. The trait would emit `Ban created/updated` skeleton lines that
 * have no human-readable subject — this service writes the canonical row
 * explicitly so the audit timeline reads "Alice banned Bob (temporary, reason: …)".
 *
 * Threat refs:
 *   - T-09-07-03 (R) — every ban issuance/lift writes an activity_log row;
 *     repudiation impossible.
 *   - T-09-07-04 (S) — issuedBy / liftedBy are REQUIRED parameters (non-nullable);
 *     callers MUST pass auth()->user() or a domain user.
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class BanService
{
    /** @var list<string> */
    public const BAN_TYPES = ['temporary', 'permanent'];

    /**
     * Issue a ban against $user, attributing the action to $issuedBy.
     *
     * @throws InvalidArgumentException When $banType is not in BAN_TYPES, or
     *                                  $banType='temporary' without an expiry.
     */
    public function issue(
        User $user,
        string $reason,
        string $banType,
        ?Carbon $expiresAt,
        User $issuedBy,
    ): Ban {
        if (! in_array($banType, self::BAN_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid ban_type %s (allowed: %s).',
                $banType,
                implode(', ', self::BAN_TYPES),
            ));
        }

        // Permanent bans MUST have null expiry — the active() scope checks
        // (lifted_at IS NULL AND (expires_at IS NULL OR expires_at > now())),
        // so a non-null expires_at on a "permanent" ban would silently expire it.
        if ($banType === 'permanent') {
            $expiresAt = null;
        }

        if ($banType === 'temporary' && $expiresAt === null) {
            throw new InvalidArgumentException(
                'Temporary bans require a non-null expires_at.'
            );
        }

        /** @var Ban $ban */
        $ban = DB::transaction(function () use ($user, $reason, $banType, $expiresAt, $issuedBy): Ban {
            /** @var Ban $created */
            $created = Ban::query()->create([
                'user_id' => $user->id,
                'ban_type' => $banType,
                'reason' => $reason,
                'expires_at' => $expiresAt,
                'issued_by_user_id' => $issuedBy->id,
            ]);

            activity()
                ->causedBy($issuedBy)
                ->performedOn($user)
                ->withProperties([
                    'ban_id' => $created->id,
                    'ban_type' => $banType,
                    'reason' => $reason,
                    'expires_at' => $expiresAt?->toIso8601String(),
                ])
                ->log('user.banned');

            return $created;
        });

        return $ban;
    }

    /**
     * Lift an existing $ban, attributing the action to $liftedBy.
     *
     * Idempotent on already-lifted bans: re-lifting overwrites lifted_at /
     * lifted_by / lift_reason. Callers should generally filter via
     * `Ban::active()` before invoking lift, but the service does not guard
     * against double-lift — the audit-log row is the source of truth.
     */
    public function lift(Ban $ban, User $liftedBy, string $liftReason): Ban
    {
        // Resolve the banned user OUTSIDE the closure so PHPStan can narrow the
        // type: $ban->user is BelongsTo<User, ...> -> User|null in static analysis
        // even though every Ban row has a non-null user_id FK (cascadeOnDelete).
        // Defensive null-coalesce to the issuer is impossible here — the subject
        // MUST be the original banned user for the audit trail to make sense, so
        // we surface this as a runtime check and let the caller catch the
        // upstream FK integrity violation if it ever occurs.
        $subject = $ban->user ?? throw new \RuntimeException(
            sprintf('Ban %d has no associated user (FK integrity failure).', (int) $ban->id),
        );

        DB::transaction(function () use ($ban, $liftedBy, $liftReason, $subject): void {
            $ban->update([
                'lifted_at' => now(),
                'lifted_by_user_id' => $liftedBy->id,
                'lift_reason' => $liftReason,
            ]);

            activity()
                ->causedBy($liftedBy)
                ->performedOn($subject)
                ->withProperties([
                    'ban_id' => $ban->id,
                    'lift_reason' => $liftReason,
                ])
                ->log('user.ban_lifted');
        });

        return $ban->fresh() ?? $ban;
    }

    /**
     * True when the user has at least one active (unlifted, unexpired) ban row.
     *
     * Delegates to User::activeBan() (plan 09-03) so the active() scope
     * definition is owned by the model — the service is a thin convenience.
     */
    public function isCurrentlyBanned(User $user): bool
    {
        return $user->activeBan() !== null;
    }
}
