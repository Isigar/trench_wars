<?php

declare(strict_types=1);

namespace App\Jobs\Rcon;

use App\Models\MatchServer;
use App\Services\Rcon\CrconHealthProbe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Source: .planning/phases/08-rcon-automation/08-09-PLAN.md task 2 +
 *         <interfaces> TestMatchServerConnectionJob block.
 *
 * Async Horizon job — invoked from the Filament MatchServerResource "Test
 * Connection" table action (T-08-09-02 mitigation: PHP-FPM has a 30s timeout,
 * slow CRCON would hit it). Admin sees an instant "queued" notification; this
 * job runs CrconHealthProbe and updates `last_test_*` columns when the probe
 * returns.
 *
 * Constructor takes the MatchServer UUID (NOT the model instance) — queue
 * jobs serialise their args to Redis. Carrying an Eloquent model would risk
 * ModelNotFoundException on rehydrate if the row was deleted between dispatch
 * and handle (same Phase 5 SyncDiscordRolesJob idiom + plan 08-08's
 * CloseMatchJob docblock).
 */
final class TestMatchServerConnectionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $matchServerId) {}

    public function handle(CrconHealthProbe $probe): void
    {
        $server = MatchServer::findOrFail($this->matchServerId);

        $result = $probe->probe($server);

        $server->update([
            'last_test_at' => now(),
            'last_test_status' => $result['status'],
            'last_test_error' => $result['error'],
        ]);
    }
}
