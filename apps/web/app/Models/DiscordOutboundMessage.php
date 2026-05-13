<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\DiscordOutboundMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/05-discord-bot-v1/05-02-PLAN.md task 2 + 05-RESEARCH.md Pattern 4.
 *
 * Durable outbox row for every Discord-bound side effect. Web writes rows in `pending`
 * state; the bot polls `scopeDispatchable()` (status=pending AND backoff_until null OR past),
 * claims a row by transitioning it to `dispatching` (plan 05-04 BotApiOutboundController),
 * then transitions to `sent` (with sent_message_id) or `failed` (with last_error).
 *
 * State machine:
 *   pending --(claim by bot poll)--> dispatching
 *   dispatching --(Discord ACK)----> sent
 *   dispatching --(Discord NACK)---> failed   (+last_error)
 *   failed --(backoff retry)-------> pending  (orchestrated by plan 05-04)
 *
 * LogsActivity (D-012 + CLAUDE.md §6): every create + status transition is appended to
 * activity_log and surfaced via Filament admin (plan 05-07). Never edit/delete activity_log
 * rows via UI — append-only contract.
 */
class DiscordOutboundMessage extends Model
{
    /** @use HasFactory<DiscordOutboundMessageFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'channel_id',
        'message_type',
        'status',
        'payload',
        'attempts',
        'last_error',
        'sent_message_id',
        'causer_user_id',
        'backoff_until',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'backoff_until' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event): string => "DiscordOutboundMessage {$event}");
    }

    /** @return BelongsTo<User, $this> */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_user_id');
    }

    /**
     * Rows the bot poller hasn't claimed yet (Phase 5 plan 05-04 contract).
     *
     * @param  Builder<DiscordOutboundMessage>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /**
     * Rows ready for the bot to claim — pending AND not deferred by backoff.
     *
     * Compound predicate:
     *   status = pending
     *   AND (backoff_until IS NULL OR backoff_until <= now())
     *
     * Index (status, backoff_until) on the table supports this query.
     *
     * @param  Builder<DiscordOutboundMessage>  $query
     */
    public function scopeDispatchable(Builder $query): void
    {
        $query->where('status', 'pending')
            ->where(function (Builder $sub): void {
                $sub->whereNull('backoff_until')
                    ->orWhere('backoff_until', '<=', now());
            });
    }
}
