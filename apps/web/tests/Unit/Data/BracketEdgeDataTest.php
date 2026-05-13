<?php

declare(strict_types=1);

/*
| Wave 5 GREEN — replaces Wave 0 RED stub from plan 06-01.
| Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md Task 1.
|
| Covers BracketEdgeData (compact value DTO):
|   - constructor shape (from/to ids + slot + type)
|   - 'winner' + 'loser' type values
|   - 'a' + 'b' slot values
|   - #[TypeScript] attribute emission
|
| The composition that PRODUCES BracketEdgeData rows from advances_to / loser_advances_to
| pointers is covered end-to-end by PublicTournamentDataTest — this file
| asserts the leaf DTO shape only.
*/

use App\Data\BracketEdgeData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

it('constructs a winner-type BracketEdgeData with slot a', function (): void {
    $edge = new BracketEdgeData(
        from_bracket_id: 'b-1',
        to_bracket_id: 'b-3',
        to_slot: 'a',
        type: 'winner',
    );

    expect($edge->from_bracket_id)->toBe('b-1')
        ->and($edge->to_bracket_id)->toBe('b-3')
        ->and($edge->to_slot)->toBe('a')
        ->and($edge->type)->toBe('winner');
});

it('constructs a loser-type BracketEdgeData with slot b (double-elim drop chain)', function (): void {
    $edge = new BracketEdgeData(
        from_bracket_id: 'wb-2',
        to_bracket_id: 'lb-1',
        to_slot: 'b',
        type: 'loser',
    );

    expect($edge->type)->toBe('loser')
        ->and($edge->to_slot)->toBe('b');
});

it('emits #[TypeScript] attribute resolved by transformer reflection', function (): void {
    $attributes = (new ReflectionClass(BracketEdgeData::class))->getAttributes(TypeScript::class);

    expect($attributes)->not->toBeEmpty();
});
