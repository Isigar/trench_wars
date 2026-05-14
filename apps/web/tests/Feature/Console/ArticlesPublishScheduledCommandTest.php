<?php

declare(strict_types=1);

use App\Models\Article;
use Illuminate\Support\Facades\Artisan;

/*
| Source: .planning/phases/07-cms/07-07-PLAN.md Task 2.
|
| Narrow ArticlesPublishScheduledCommand surface tests:
|   - command is registered + callable via Artisan::call
|   - zero scheduled rows  → exit code 0 + 'Published 0 article(s).' output
|   - one scheduled row    → exit code 0 + 'Published 1 article(s).' output
|   - container resolution → ArticlePublishService is auto-resolved and its
|                            publishDue() is invoked by handle()
|
| The end-to-end workflow (observer chain — Event sync + article_announce
| outbound + activity_log) is covered separately by ArticlePublishWorkflowTest;
| this file isolates the command surface so a regression in just the artisan
| signature or service binding surfaces immediately.
*/

beforeEach(function (): void {
    config(['discord.league_announce_channel_id' => '0123456789012345']); // mock snowflake
});

it('registers the articles:publish-scheduled command in the artisan registry', function (): void {
    // Artisan::call invokes the command by signature. If the command class were
    // unregistered (e.g. namespace typo, missing AppServiceProvider boot), this
    // would throw \Symfony\Component\Console\Exception\CommandNotFoundException.
    $exitCode = Artisan::call('articles:publish-scheduled');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->not->toBe('')
        ->and($output)->toContain('Published');
});

it('returns exit code 0 with zero articles', function (): void {
    expect(Article::where('status', 'scheduled')->count())->toBe(0);

    $exitCode = Artisan::call('articles:publish-scheduled');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Published 0 article(s).');
});

it('returns exit code 0 with one published article', function (): void {
    Article::factory()->create([
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'allow_discord_announce' => false,
    ]);

    $exitCode = Artisan::call('articles:publish-scheduled');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Published 1 article(s).');
});

it('uses ArticlePublishService via container resolution', function (): void {
    // Verify the command resolves ArticlePublishService from the container by
    // creating a scheduled article and asserting the service's publishDue()
    // ran (status flipped to published). The handle() method signature
    // `handle(ArticlePublishService $service)` is what triggers the auto-wire;
    // if Laravel's container failed to inject, Artisan would throw at the
    // handle() level before the body ran.
    //
    // NB: a pure mock-double substitution would require unsealing the `final`
    // keyword on the prod class. We assert container resolution indirectly
    // here — the row would not flip if handle() was not called or if the
    // injected service was not the real one (verified by side effect).
    $a = Article::factory()->create([
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'allow_discord_announce' => false,
    ]);

    $exitCode = Artisan::call('articles:publish-scheduled');

    expect($exitCode)->toBe(0)
        ->and($a->fresh()->status)->toBe('published')
        // Output proves $this->info ran with the count returned by publishDue.
        ->and(Artisan::output())->toContain('Published 1 article(s).');
});
