<?php

declare(strict_types=1);

namespace App\Http\Controllers\BotApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarkOutboundFailedRequest;
use App\Http\Requests\MarkOutboundSentRequest;
use App\Models\DiscordOutboundMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Source: 05-04-PLAN.md <interfaces> BotApiOutboundController block + 05-RESEARCH.md
 * Pattern 4 (atomic claim — DB::transaction + lockForUpdate + UPDATE in one shot).
 *
 * Two-replica safety (T-05-04-03):
 *   pending() acquires a row-level exclusive lock on each pending row, flips
 *   status -> dispatching, increments attempts, and returns the claimed rows.
 *   Two concurrent calls (e.g. two bot replicas during a rolling deploy) cannot
 *   double-claim the same row because the lock serializes them — the second
 *   transaction sees status=dispatching and skips the row.
 *
 * Replay safety (T-05-04-04):
 *   markSent / markFailed assert status === 'dispatching' before mutating.
 *   Already-sent or already-failed rows return 422 outbound_not_dispatching.
 *
 * Backoff schedule (markFailed retry path):
 *   attempts=1 -> backoff_until = now + 1s
 *   attempts=2 -> backoff_until = now + 5s
 *   attempts=3 -> TERMINAL — status=failed (no further retries)
 *   The [1, 5, 15, 60, 300] index reads attempts-1 (zero-based); attempts < 3
 *   retries, attempts >= 3 transitions to failed.
 *
 * DoS guard (T-05-04-07):
 *   `limit` query param is clamped to max 50; default 20.
 *
 * NO bot.acts-as middleware on this controller's routes — the outbound delivery
 * cycle is the bot acting AS ITSELF (not on behalf of a human). LogsActivity
 * still records the status transitions; the causer is the bot service user.
 */
final class BotApiOutboundController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query('limit', 20)));

        $rows = DB::transaction(function () use ($limit) {
            /** @var Collection<int, DiscordOutboundMessage> $pending */
            $pending = DiscordOutboundMessage::query()
                ->dispatchable()
                ->orderBy('created_at')
                ->lockForUpdate()
                ->limit($limit)
                ->get();

            foreach ($pending as $row) {
                $row->update([
                    'status' => 'dispatching',
                    'attempts' => $row->attempts + 1,
                ]);
            }

            return $pending->fresh();
        });

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function markSent(MarkOutboundSentRequest $request, string $id): JsonResponse
    {
        /** @var DiscordOutboundMessage $row */
        $row = DiscordOutboundMessage::query()->findOrFail($id);

        if ($row->status !== 'dispatching') {
            return response()->json([
                'error' => 'outbound_not_dispatching',
                'message' => __('bot.errors.outbound_not_dispatching'),
            ], 422);
        }

        /** @var array{sent_message_id: string} $validated */
        $validated = $request->validated();

        $row->update([
            'status' => 'sent',
            'sent_message_id' => $validated['sent_message_id'],
            'last_error' => null,
        ]);

        return response()->json([
            'data' => $row->fresh(),
        ]);
    }

    public function markFailed(MarkOutboundFailedRequest $request, string $id): JsonResponse
    {
        /** @var DiscordOutboundMessage $row */
        $row = DiscordOutboundMessage::query()->findOrFail($id);

        if ($row->status !== 'dispatching') {
            return response()->json([
                'error' => 'outbound_not_dispatching',
                'message' => __('bot.errors.outbound_not_dispatching'),
            ], 422);
        }

        /** @var array{last_error: string} $validated */
        $validated = $request->validated();

        // Exponential backoff schedule [1, 5, 15, 60, 300] seconds. Index by
        // attempts-1 (zero-based). attempts >= 3 is terminal (failed status).
        $schedule = [1, 5, 15, 60, 300];

        if ($row->attempts >= 3) {
            $row->update([
                'status' => 'failed',
                'last_error' => $validated['last_error'],
            ]);
        } else {
            $backoffSeconds = $schedule[$row->attempts - 1] ?? 600;
            $row->update([
                'status' => 'pending',
                'last_error' => $validated['last_error'],
                'backoff_until' => now()->addSeconds($backoffSeconds),
            ]);
        }

        return response()->json([
            'data' => $row->fresh(),
        ]);
    }
}
