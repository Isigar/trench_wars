<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\PlayerPrivacy;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service profile-privacy editor (D-018, REQ-goal-public-profiles).
 *
 * D-018 promises "user-controllable" per-section + global-tier privacy, but the
 * only write surface that existed was the admin-gated Filament PlayerResource —
 * a regular member could never change their own `show_to` tier or section
 * toggles off the seeded defaults. This controller is the missing member-facing
 * entry point, mirroring the Account/NotificationPreferences pattern (09-06).
 *
 * Scope safety: the privacy row is resolved from the authenticated user's own
 * Player (HasOne), so update() can only ever touch the caller's own row.
 * ProvisionFirstLogin guarantees a Player + PlayerPrivacy exist after any login;
 * the resolve step defensively materialises the D-018 defaults if absent.
 */
class PrivacyController extends Controller
{
    /** @var list<string> Global visibility tiers (player_privacy.show_to enum). */
    private const TIERS = ['public', 'community', 'clan', 'private'];

    /** @var list<string> Per-section visibility toggles. */
    private const SECTIONS = [
        'show_real_name',
        'show_discord_tag',
        'show_clan_history',
        'show_match_history',
        'show_stats',
    ];

    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $privacy = $this->resolvePrivacy($user);

        return Inertia::render('Account/Privacy', [
            'privacy' => [
                'show_to' => $privacy->show_to,
                'show_real_name' => $privacy->show_real_name,
                'show_discord_tag' => $privacy->show_discord_tag,
                'show_clan_history' => $privacy->show_clan_history,
                'show_match_history' => $privacy->show_match_history,
                'show_stats' => $privacy->show_stats,
            ],
            'tiers' => self::TIERS,
            'sections' => self::SECTIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validate([
            'show_to' => ['required', 'string', 'in:' . implode(',', self::TIERS)],
            'show_real_name' => ['required', 'boolean'],
            'show_discord_tag' => ['required', 'boolean'],
            'show_clan_history' => ['required', 'boolean'],
            'show_match_history' => ['required', 'boolean'],
            'show_stats' => ['required', 'boolean'],
        ]);

        $this->resolvePrivacy($user)->update($validated);

        return back()->with('success', __('players.privacy.editor.saved'));
    }

    /**
     * Resolve (or defensively provision) the authenticated user's own privacy row.
     * Mirrors the D-018 first-login defaults used by ProvisionFirstLogin.
     */
    private function resolvePrivacy(User $user): PlayerPrivacy
    {
        $player = $user->player;
        abort_if($player === null, 404);

        return $player->privacy ?? PlayerPrivacy::create([
            'player_id' => $player->id,
            'show_to' => 'community',
            'show_real_name' => false,
            'show_discord_tag' => true,
            'show_clan_history' => true,
            'show_match_history' => true,
            'show_stats' => true,
        ]);
    }
}
