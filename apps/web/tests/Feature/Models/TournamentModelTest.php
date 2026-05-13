<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Game;
use App\Models\GameMatchType;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md task 2.
| Replaces the Wave 0 RED stub from plan 06-01.
|
| Bare Pest functional convention (Phase 5 D-05-01-C canonical): no namespace,
| no per-file uses() — Pest.php autowires TestCase + RefreshDatabase via
| uses(...)->in('Feature').
*/

it('creates a valid tournament via factory', function (): void {
    $tournament = Tournament::factory()->create();

    expect($tournament->exists)->toBeTrue();
    expect($tournament->status)->toBe('draft');
    expect($tournament->is_public)->toBeTrue();
    expect($tournament->slug)->not->toBe('');
});

it('round-trips title + description through HasTranslations', function (): void {
    $tournament = Tournament::factory()->create([
        'title' => ['en' => 'Spring Cup'],
        'description' => ['en' => 'Single-elim cup, best-of-three.'],
    ]);

    $tournament->setTranslation('title', 'en', 'Spring Cup 2026');
    $tournament->save();

    $reloaded = $tournament->fresh();
    expect($reloaded?->getTranslation('title', 'en'))->toBe('Spring Cup 2026');
    expect($reloaded?->getTranslation('description', 'en'))->toBe('Single-elim cup, best-of-three.');
});

it('casts settings to array, max_participants to int, is_public to bool, starts/ends to datetime', function (): void {
    $tournament = Tournament::factory()->create([
        'settings' => ['rounds' => 5, 'tiebreaker' => 'buchholz'],
        'max_participants' => 16,
        'is_public' => false,
        'starts_at' => '2026-06-01 12:00:00',
        'ends_at' => '2026-06-30 22:00:00',
    ]);

    $reloaded = $tournament->fresh();
    expect($reloaded?->settings)->toBe(['rounds' => 5, 'tiebreaker' => 'buchholz']);
    expect($reloaded?->max_participants)->toBe(16);
    expect($reloaded?->is_public)->toBeFalse();
    expect($reloaded?->starts_at)->toBeInstanceOf(Carbon::class);
    expect($reloaded?->ends_at)->toBeInstanceOf(Carbon::class);
});

it('enforces tournaments_format_check at the DB layer', function (): void {
    expect(fn () => Tournament::factory()->create(['format' => 'mystery_box']))
        ->toThrow(QueryException::class);
});

it('enforces tournaments_status_check at the DB layer', function (): void {
    expect(fn () => Tournament::factory()->create(['status' => 'banana']))
        ->toThrow(QueryException::class);
});

it('enforces UNIQUE on slug', function (): void {
    Tournament::factory()->create(['slug' => 'cup-2026']);

    expect(fn () => Tournament::factory()->create(['slug' => 'cup-2026']))
        ->toThrow(QueryException::class);
});

it('uses slug as the route key', function (): void {
    $tournament = Tournament::factory()->create(['slug' => 'autumn-cup']);

    expect($tournament->getRouteKeyName())->toBe('slug');
    expect($tournament->getRouteKey())->toBe('autumn-cup');
});

it('exposes game, organiser, defaultGameMatchType BelongsTo relations', function (): void {
    $game = Game::factory()->create();
    $organiser = User::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();

    $tournament = Tournament::factory()->create([
        'game_id' => $game->id,
        'organiser_user_id' => $organiser->id,
        'default_game_match_type_id' => $matchType->id,
    ]);

    expect($tournament->game?->id)->toBe($game->id);
    expect($tournament->organiser?->id)->toBe($organiser->id);
    expect($tournament->defaultGameMatchType?->id)->toBe($matchType->id);
});

it('exposes participants, stages, standings HasMany relations', function (): void {
    $tournament = Tournament::factory()->create();
    $participant = TournamentParticipant::factory()->for($tournament)->create();
    $stage = TournamentStage::factory()->for($tournament)->create();
    $standing = TournamentStanding::factory()
        ->for($tournament)
        ->for($stage, 'stage')
        ->for($participant, 'participant')
        ->create();

    $reloaded = $tournament->fresh();
    expect($reloaded?->participants->pluck('id')->all())->toContain($participant->id);
    expect($reloaded?->stages->pluck('id')->all())->toContain($stage->id);
    expect($reloaded?->standings->pluck('id')->all())->toContain($standing->id);
});

it('orders stages by ordinal', function (): void {
    $tournament = Tournament::factory()->create();
    $stage3 = TournamentStage::factory()->for($tournament)->create(['ordinal' => 3]);
    $stage1 = TournamentStage::factory()->for($tournament)->create(['ordinal' => 1]);
    $stage2 = TournamentStage::factory()->for($tournament)->create(['ordinal' => 2]);

    $stages = $tournament->fresh()?->stages;
    expect($stages?->pluck('id')->all())->toBe([$stage1->id, $stage2->id, $stage3->id]);
});

it('exposes event MorphOne relation (auto-populated by TournamentObserver since 06-10)', function (): void {
    // Plan 06-10 fills the TournamentObserver::saved() body so creating a
    // public Tournament auto-creates a polymorphic Event row. We assert the
    // MorphOne relation resolves the auto-created row.
    $tournament = Tournament::factory()->create(['is_public' => true]);

    expect($tournament->fresh()?->event?->eventable_type)->toBe(Tournament::class);

    // Private tournaments do NOT create an Event row (observer guard).
    $private = Tournament::factory()->create(['is_public' => false]);
    expect($private->fresh()?->event)->toBeNull();
});

it('logs activity on create (D-012)', function (): void {
    $tournament = Tournament::factory()->create();

    $exists = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('event', 'created')
        ->exists();

    expect($exists)->toBeTrue();
});

it('logs activity on update (LogsActivity logOnlyDirty)', function (): void {
    $tournament = Tournament::factory()->create(['status' => 'draft']);
    $tournament->update(['status' => 'registering']);

    $exists = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('event', 'updated')
        ->exists();

    expect($exists)->toBeTrue();
});
