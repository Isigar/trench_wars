<?php

declare(strict_types=1);

/*
| Raise-a-dispute entry point (POST /matches/{match}/disputes).
|
| Closes the HIGH reachability gap where DisputeService::open had no production
| caller — the moderator dispute queue had zero reachable input. These tests
| assert a participant/organiser can open a dispute on a played match, that the
| row lands in match_disputes with status=open, and that the eligibility gate
| (played match + participant/organiser, one-open-per-match) holds.
*/

use App\Models\GameMatch;
use App\Models\MatchDispute;
use App\Models\MatchSlot;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function playedMatch(): GameMatch
{
    return GameMatch::factory()->create(['status' => 'played', 'is_public' => true]);
}

it('lets a slot participant open a dispute on a played match', function (): void {
    $match = playedMatch();
    $participant = User::factory()->create();
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'occupant_user_id' => $participant->id,
    ]);

    $this->actingAs($participant)
        ->post(route('matches.disputes.store', $match->id), [
            'body' => 'The score was recorded backwards — we won that match.',
        ])
        ->assertRedirect(route('matches.show', $match->id));

    $dispute = MatchDispute::where('match_id', $match->id)->first();
    expect($dispute)->not->toBeNull()
        ->and($dispute->raised_by_user_id)->toBe($participant->id)
        ->and($dispute->status)->toBe('open')
        ->and($dispute->body)->toContain('recorded backwards');
});

it('lets the organiser open a dispute even without a slot', function (): void {
    $organiser = User::factory()->create();
    $match = GameMatch::factory()->for($organiser, 'organiser')->create(['status' => 'played']);

    $this->actingAs($organiser)
        ->post(route('matches.disputes.store', $match->id), [
            'body' => 'A whole role group is missing from the recorded result.',
        ])
        ->assertRedirect(route('matches.show', $match->id));

    expect(MatchDispute::where('match_id', $match->id)->where('raised_by_user_id', $organiser->id)->exists())->toBeTrue();
});

it('forbids a non-participant from opening a dispute (403)', function (): void {
    $match = playedMatch();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('matches.disputes.store', $match->id), [
            'body' => 'I was not even in this match but want to complain.',
        ])
        ->assertStatus(403);

    expect(MatchDispute::where('match_id', $match->id)->count())->toBe(0);
});

it('forbids disputing a match that has not been played yet (403)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $participant = User::factory()->create();
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'occupant_user_id' => $participant->id,
    ]);

    $this->actingAs($participant)
        ->post(route('matches.disputes.store', $match->id), [
            'body' => 'This match has not even happened yet.',
        ])
        ->assertStatus(403);

    expect(MatchDispute::where('match_id', $match->id)->count())->toBe(0);
});

it('rejects a too-short dispute body', function (): void {
    $match = playedMatch();
    $participant = User::factory()->create();
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'occupant_user_id' => $participant->id,
    ]);

    $this->actingAs($participant)
        ->post(route('matches.disputes.store', $match->id), ['body' => 'no'])
        ->assertSessionHasErrors('body');

    expect(MatchDispute::where('match_id', $match->id)->count())->toBe(0);
});

it('rejects a second open dispute from the same user on the same match', function (): void {
    $match = playedMatch();
    $participant = User::factory()->create();
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'occupant_user_id' => $participant->id,
    ]);

    $this->actingAs($participant)
        ->post(route('matches.disputes.store', $match->id), [
            'body' => 'First dispute — the result is wrong.',
        ])
        ->assertRedirect();

    $this->actingAs($participant)
        ->post(route('matches.disputes.store', $match->id), [
            'body' => 'Second dispute attempt — should be blocked.',
        ])
        ->assertSessionHasErrors('body');

    expect(MatchDispute::where('match_id', $match->id)->count())->toBe(1);
});

it('requires authentication', function (): void {
    $match = playedMatch();
    $this->post(route('matches.disputes.store', $match->id), ['body' => 'guest attempt body here'])
        ->assertRedirect();

    expect(MatchDispute::count())->toBe(0);
});

it('exposes canDispute=true to a participant on the match detail page', function (): void {
    $match = playedMatch();
    $participant = User::factory()->create();
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'occupant_user_id' => $participant->id,
    ]);

    $this->actingAs($participant)
        ->get(route('matches.show', $match->id))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('canDispute', true)
                ->where('hasOpenDispute', false)
        );
});

it('flips hasOpenDispute=true after the participant opens one', function (): void {
    $match = playedMatch();
    $participant = User::factory()->create();
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'occupant_user_id' => $participant->id,
    ]);

    $this->actingAs($participant)->post(route('matches.disputes.store', $match->id), [
        'body' => 'Opening a dispute to flip the UI flag.',
    ]);

    $this->actingAs($participant)
        ->get(route('matches.show', $match->id))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('hasOpenDispute', true));
});
