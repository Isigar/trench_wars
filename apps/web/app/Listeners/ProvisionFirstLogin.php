<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Source: 01-CONTEXT.md "First-login provisioning" + 01-RESEARCH.md Pattern 1.
 *
 * Idempotent: re-login does NOT create duplicate Player/PlayerPrivacy rows.
 * Atomic: all writes happen inside DB::transaction so a failure rolls back fully.
 *
 * Threat T-1-03 mitigation: discord_id has a UNIQUE constraint at the DB level
 * (plan 10), and Player creation is gated by a `player === null` check.
 * Concurrent first-login attempts on the same discord_id are absorbed by the
 * User::updateOrCreate pre-step that already ran in DiscordController@callback.
 */
class ProvisionFirstLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        DB::transaction(function () use ($user): void {
            // Touch last_login_at on every login.
            $user->forceFill(['last_login_at' => now()])->save();

            // Create the Player + PlayerPrivacy ONLY on first login.
            $user->load('player');

            if ($user->player !== null) {
                return;
            }

            // The `if ($user->player !== null)` check above is read-then-write;
            // under concurrent first-login (two browser tabs, retried request),
            // two listener invocations can both see `null` and race to insert.
            // The `players.user_id` UNIQUE constraint catches it at the DB
            // layer, but a raw QueryException would bubble up as a 500 to the
            // second client even though the first request succeeded. Swallow
            // the unique-violation case — the parallel request already
            // provisioned the row, which is exactly what we wanted.
            try {
                /** @var Player $player */
                $player = $user->player()->create([
                    'slug' => Str::slug($user->username) . '-' . Str::lower(Str::random(4)),
                    'display_name' => null,
                ]);

                // D-018 defaults — show_real_name=false (sensitive PII), all others true,
                // global tier 'community' (visible to logged-in league members).
                PlayerPrivacy::create([
                    'player_id' => $player->id,
                    'show_to' => 'community',
                    'show_real_name' => false,
                    'show_discord_tag' => true,
                    'show_clan_history' => true,
                    'show_match_history' => true,
                    'show_stats' => true,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Concurrent first-login race — the parallel request already
                // provisioned. Idempotent; nothing to do.
            }
        });
    }
}
