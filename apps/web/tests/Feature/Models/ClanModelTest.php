<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanTag;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

it('creates a clan with default status active', function (): void {
    $clan = Clan::factory()->create();
    expect($clan->status)->toBe('active');
    expect($clan->slug)->not->toBeNull();
    expect($clan->owner_user_id)->not->toBeNull();
});

it('enforces status CHECK constraint', function (): void {
    expect(fn () => Clan::factory()->create(['status' => 'invalid']))
        ->toThrow(QueryException::class);
});

it('rounds-trip description through HasTranslations', function (): void {
    $clan = Clan::factory()->create(['description' => ['en' => 'Original']]);
    $clan->setTranslation('description', 'en', 'Hi');
    $clan->save();

    $reloaded = $clan->fresh();
    expect($reloaded->getTranslation('description', 'en'))->toBe('Hi');
});

it('attaches and detaches clan tags via BelongsToMany', function (): void {
    $clan = Clan::factory()->create();
    $tag = ClanTag::factory()->create();

    $clan->tags()->attach($tag->id);
    expect($clan->tags()->count())->toBe(1);

    $clan->tags()->detach($tag->id);
    expect($clan->tags()->count())->toBe(0);
});

it('logs activity on create (D-012)', function (): void {
    $clan = Clan::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', Clan::class)
        ->where('subject_id', $clan->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});
