<?php

declare(strict_types=1);

use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Services\Brackets\BracketGeneratorService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Sequence;

/*
| Source: 06-07-PLAN.md Task 1 — replaces Wave 0 RED stub from 06-01.
|
| Covers DoubleEliminationGenerator via BracketGeneratorService dispatch:
|   - RESEARCH Pattern 6 Burton variant (W-bracket reuse + L-bracket Burton mapping)
|   - N=8 hardcoded loser-drop chain verified row-by-row
|   - 3 stages: winners-bracket, losers-bracket, grand-final
|   - Grand-final stage settings.grand_final_reset propagated from tournament.settings
|   - W-bracket reuse of SingleEliminationGenerator::layoutInStage()
|   - W-final + L-final advances_to_bracket_id → grand-final bracket
|
| Naming note (D-04-03-A): no `match` expression appears here; the GameMatch
| alias-on-import pattern is not needed.
*/

/**
 * Helper: create $n active, seeded participants for $tournament with seeds 1..N.
 *
 * @return EloquentCollection<int, TournamentParticipant>
 */
function makeSeededDoubleElimParticipants(Tournament $tournament, int $n): EloquentCollection
{
    /** @var EloquentCollection<int, TournamentParticipant> $created */
    $created = TournamentParticipant::factory()
        ->for($tournament)
        ->count($n)
        ->state(new Sequence(...array_map(
            fn (int $i): array => ['seed' => $i + 1, 'status' => 'active'],
            range(0, $n - 1)
        )))
        ->create();

    return $created;
}

// ---------------------------------------------------------------------------
// 8-participant happy path — Burton variant 3-stage layout
// ---------------------------------------------------------------------------

it('generates an 8-participant double-elim with 3 stages (W + L + GF)', function (): void {
    $tournament = Tournament::factory()->ofFormat('double_elimination')->inStatus('seeded')->create();
    makeSeededDoubleElimParticipants($tournament, 8);

    app(BracketGeneratorService::class)->generate($tournament);

    expect($tournament->stages()->count())->toBe(3);

    /** @var TournamentStage $wStage */
    $wStage = $tournament->stages()->where('type', 'winners-bracket')->firstOrFail();
    /** @var TournamentStage $lStage */
    $lStage = $tournament->stages()->where('type', 'losers-bracket')->firstOrFail();
    /** @var TournamentStage $gfStage */
    $gfStage = $tournament->stages()->where('type', 'grand-final')->firstOrFail();

    // Ordinals are 1/2/3 in canonical order.
    expect($wStage->ordinal)->toBe(1);
    expect($lStage->ordinal)->toBe(2);
    expect($gfStage->ordinal)->toBe(3);

    // W-bracket: 4 + 2 + 1 = 7 brackets (reused from SingleEliminationGenerator).
    expect($wStage->brackets()->count())->toBe(7);
    expect($wStage->brackets()->where('round_number', 1)->count())->toBe(4);
    expect($wStage->brackets()->where('round_number', 2)->count())->toBe(2);
    expect($wStage->brackets()->where('round_number', 3)->count())->toBe(1);

    // L-bracket: 2 + 2 + 1 + 1 = 6 brackets (Burton variant).
    expect($lStage->brackets()->count())->toBe(6);
    expect($lStage->brackets()->where('round_number', 1)->count())->toBe(2);
    expect($lStage->brackets()->where('round_number', 2)->count())->toBe(2);
    expect($lStage->brackets()->where('round_number', 3)->count())->toBe(1);
    expect($lStage->brackets()->where('round_number', 4)->count())->toBe(1);

    // Grand final: 1 bracket with null participants (filled by advancement).
    expect($gfStage->brackets()->count())->toBe(1);
    /** @var TournamentBracket $gfBracket */
    $gfBracket = $gfStage->brackets()->firstOrFail();
    expect($gfBracket->participant_a_id)->toBeNull();
    expect($gfBracket->participant_b_id)->toBeNull();
});

it('wires N=8 W-bracket → L-bracket loser-drop chain per Burton variant', function (): void {
    $tournament = Tournament::factory()->ofFormat('double_elimination')->inStatus('seeded')->create();
    makeSeededDoubleElimParticipants($tournament, 8);

    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $wStage */
    $wStage = $tournament->stages()->where('type', 'winners-bracket')->firstOrFail();
    /** @var TournamentStage $lStage */
    $lStage = $tournament->stages()->where('type', 'losers-bracket')->firstOrFail();

    $wR1 = $wStage->brackets()->where('round_number', 1)->orderBy('position')->get();
    $wR2 = $wStage->brackets()->where('round_number', 2)->orderBy('position')->get();
    $wR3 = $wStage->brackets()->where('round_number', 3)->orderBy('position')->get();

    $lR1 = $lStage->brackets()->where('round_number', 1)->orderBy('position')->get();
    $lR2 = $lStage->brackets()->where('round_number', 2)->orderBy('position')->get();
    $lR4 = $lStage->brackets()->where('round_number', 4)->orderBy('position')->get();

    // Burton drop mapping for N=8:
    //   W-r1-p1 + W-r1-p2 losers → LB-r1-p1
    //   W-r1-p3 + W-r1-p4 losers → LB-r1-p2
    expect($wR1[0]->loser_advances_to_bracket_id)->toBe($lR1[0]->id);
    expect($wR1[1]->loser_advances_to_bracket_id)->toBe($lR1[0]->id);
    expect($wR1[2]->loser_advances_to_bracket_id)->toBe($lR1[1]->id);
    expect($wR1[3]->loser_advances_to_bracket_id)->toBe($lR1[1]->id);

    //   W-r2-p1 loser → LB-r2-p1
    //   W-r2-p2 loser → LB-r2-p2
    expect($wR2[0]->loser_advances_to_bracket_id)->toBe($lR2[0]->id);
    expect($wR2[1]->loser_advances_to_bracket_id)->toBe($lR2[1]->id);

    //   W-r3-p1 (W-final) loser → LB-r4-p1 (L-final)
    expect($wR3[0]->loser_advances_to_bracket_id)->toBe($lR4[0]->id);
});

it('wires N=8 L-bracket internal advancement chain (LB winners forward)', function (): void {
    $tournament = Tournament::factory()->ofFormat('double_elimination')->inStatus('seeded')->create();
    makeSeededDoubleElimParticipants($tournament, 8);

    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $lStage */
    $lStage = $tournament->stages()->where('type', 'losers-bracket')->firstOrFail();

    $lR1 = $lStage->brackets()->where('round_number', 1)->orderBy('position')->get();
    $lR2 = $lStage->brackets()->where('round_number', 2)->orderBy('position')->get();
    $lR3 = $lStage->brackets()->where('round_number', 3)->orderBy('position')->get();
    $lR4 = $lStage->brackets()->where('round_number', 4)->orderBy('position')->get();

    // LB-r1 (minor) → LB-r2 (major) at the same position.
    expect($lR1[0]->advances_to_bracket_id)->toBe($lR2[0]->id);
    expect($lR1[1]->advances_to_bracket_id)->toBe($lR2[1]->id);

    // LB-r2 (major) → LB-r3 (minor) via ceil(p/2). Both p=1 and p=2 fold to position 1.
    expect($lR2[0]->advances_to_bracket_id)->toBe($lR3[0]->id);
    expect($lR2[1]->advances_to_bracket_id)->toBe($lR3[0]->id);

    // LB-r3 → LB-r4 (the L-final, same position).
    expect($lR3[0]->advances_to_bracket_id)->toBe($lR4[0]->id);

    // L-final → grand-final bracket (not null).
    expect($lR4[0]->advances_to_bracket_id)->not()->toBeNull();
});

it('routes W-final + L-final advances_to → grand-final bracket', function (): void {
    $tournament = Tournament::factory()->ofFormat('double_elimination')->inStatus('seeded')->create();
    makeSeededDoubleElimParticipants($tournament, 8);

    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentBracket $gfBracket */
    $gfBracket = $tournament->stages()->where('type', 'grand-final')->firstOrFail()
        ->brackets()->firstOrFail();

    /** @var TournamentBracket $wFinal */
    $wFinal = $tournament->stages()->where('type', 'winners-bracket')->firstOrFail()
        ->brackets()->where('round_number', 3)->firstOrFail();
    /** @var TournamentBracket $lFinal */
    $lFinal = $tournament->stages()->where('type', 'losers-bracket')->firstOrFail()
        ->brackets()->where('round_number', 4)->firstOrFail();

    expect($wFinal->advances_to_bracket_id)->toBe($gfBracket->id);
    expect($lFinal->advances_to_bracket_id)->toBe($gfBracket->id);
});

it('propagates grand_final_reset from tournament.settings to gf stage settings', function (): void {
    $tournament = Tournament::factory()
        ->ofFormat('double_elimination')
        ->inStatus('seeded')
        ->state(['settings' => ['grand_final_reset' => true]])
        ->create();
    makeSeededDoubleElimParticipants($tournament, 8);

    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $gfStage */
    $gfStage = $tournament->stages()->where('type', 'grand-final')->firstOrFail();
    expect($gfStage->settings)->toBe(['grand_final_reset' => true]);
});

it('defaults gf settings.grand_final_reset to false when tournament.settings is null', function (): void {
    $tournament = Tournament::factory()->ofFormat('double_elimination')->inStatus('seeded')->create();
    makeSeededDoubleElimParticipants($tournament, 8);

    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $gfStage */
    $gfStage = $tournament->stages()->where('type', 'grand-final')->firstOrFail();
    expect($gfStage->settings)->toBe(['grand_final_reset' => false]);
});

it('rejects double-elim generation when fewer than 4 active participants exist', function (): void {
    $tournament = Tournament::factory()->ofFormat('double_elimination')->inStatus('seeded')->create();
    makeSeededDoubleElimParticipants($tournament, 3);

    expect(fn () => app(BracketGeneratorService::class)->generate($tournament))
        ->toThrow(InvalidArgumentException::class);
});
