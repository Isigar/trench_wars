<?php

declare(strict_types=1);

namespace App\Services\Brackets;

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Stub — real implementation lands in plan 06-07 (Wave 3 continuation).
 *
 * Ships in plan 06-06 so the BracketGeneratorService constructor's DI graph
 * is satisfied without forcing plan 06-07 to touch the front-door file again.
 * The body throws LogicException so any accidental dispatch under format
 * 'round_robin' fails loudly with a clear message.
 */
final class RoundRobinGenerator implements BracketGeneratorStrategy
{
    /**
     * @param  Collection<int, TournamentParticipant>  $orderedParticipants
     */
    public function generate(Tournament $tournament, Collection $orderedParticipants): void
    {
        throw new LogicException('RoundRobinGenerator not yet implemented — see plan 06-07.');
    }
}
