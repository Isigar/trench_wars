<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanInvite;
use App\Models\User;
use App\Notifications\ClanInviteReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Regression guard: the Discord DM for a clan invite must interpolate the REAL
| clan name + inviter username into the embed — the toDiscord() payload shipped
| with hardcoded '—' placeholders, so the DM contained no variables.
*/

uses(RefreshDatabase::class);

it('toDiscord embeds the real clan name and inviter username (not placeholders)', function (): void {
    $clan = Clan::factory()->create(['name' => 'Trench Raiders']);
    $inviter = User::factory()->create(['username' => 'sergeant_x']);
    $invitee = User::factory()->create(['discord_id' => '111222333444555666']);

    $invite = ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $inviter->id,
        'invited_user_id' => $invitee->id,
    ]);

    $payload = (new ClanInviteReceived($invite))->toDiscord($invitee);

    expect($payload['payload']['embed_title'])
        ->toBeString()
        ->toContain('Trench Raiders')
        ->not->toContain('—');

    expect($payload['payload']['embed_description'])
        ->toBeString()
        ->toContain('Trench Raiders')
        ->toContain('sergeant_x')
        ->not->toContain('—');
});
