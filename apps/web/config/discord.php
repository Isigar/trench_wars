<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-06-PLAN.md Task 1 (Open Question 1 LOCKED inline).
|
| Discord-side configuration that is NOT OAuth — OAuth keys live in
| config/services.php under the 'discord' key (Phase 1 plan 01-08 idiom for
| socialite providers). This file owns the runtime Discord settings the web
| app needs at message-dispatch time:
|
|   - league_announce_channel_id — the snowflake of the channel where the
|     ArticleObserver enqueues article_announce outbound rows. Resolved at
|     dispatch time by the bot worker (plan 05-11) via this config value.
|
| Per-article channel override is intentionally NOT in v1 (Open Question 1
| Recommendation): a single global league channel keeps the bot worker logic
| simple and matches the SC-5 success criterion ("Discord announce on publish
| (per-article configurable)") which is satisfied by the per-article
| allow_discord_announce toggle (07-02 column). CMS-V2 may introduce a
| per-category or per-article channel override.
|
| Threat refs:
|   - T-07-06-05 (env-var spoofing): the env value is a Railway env-group
|     secret per D-014; .env.example commits an empty string (CLAUDE.md §6).
*/

return [
    /*
    |--------------------------------------------------------------------------
    | League announce channel
    |--------------------------------------------------------------------------
    | Open Question 1 LOCKED — single global league channel where the
    | ArticleObserver routes article_announce outbound rows on publish.
    |
    | Empty string by default — observer skips the outbound row when this
    | resolves to '' (defensive — avoids dispatching to channel_id='' that the
    | bot worker has no way to map to a real channel).
    */
    'league_announce_channel_id' => env('DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID', ''),
];
