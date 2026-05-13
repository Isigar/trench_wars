// Trenchwars bot — Discord component customId encode/decode.
//
// Source: .planning/phases/05-discord-bot-v1/05-08-PLAN.md task 2 (Wave 6),
// RESEARCH §Example 2 verbatim. Pitfall 5 mitigation: Discord's customId
// budget is 100 characters per component. The short prefix scheme below
// ('m:s:', 'm:l:', 'm:o:', 'c:a:') keeps two UUIDs comfortably under that
// budget (worst case: 'm:s:' (4) + 36 + ':' (1) + 36 = 77 chars).
//
// `decodeButtonId` returns `null` on malformed input — T-05-08-04 mitigation;
// the downstream router (plan 05-10) treats null as "unknown button, ignore"
// rather than crashing the interaction handler. Strict arity checks
// (parts.length === N) ensure no overflow into the wrong variant.

export type ButtonAction =
    | { kind: 'match_signup'; matchId: string; gameRoleId: string }
    | { kind: 'match_leave'; matchId: string; gameRoleId: string }
    | { kind: 'match_open_signup_modal'; matchId: string }
    | { kind: 'clan_apply'; clanId: string };

export function encodeButtonId(a: ButtonAction): string {
    switch (a.kind) {
        case 'match_signup':
            return `m:s:${a.matchId}:${a.gameRoleId}`;
        case 'match_leave':
            return `m:l:${a.matchId}:${a.gameRoleId}`;
        case 'match_open_signup_modal':
            return `m:o:${a.matchId}`;
        case 'clan_apply':
            return `c:a:${a.clanId}`;
    }
}

export function decodeButtonId(s: string): ButtonAction | null {
    const parts = s.split(':');
    if (parts[0] === 'm' && parts[1] === 's' && parts.length === 4) {
        return { kind: 'match_signup', matchId: parts[2]!, gameRoleId: parts[3]! };
    }
    if (parts[0] === 'm' && parts[1] === 'l' && parts.length === 4) {
        return { kind: 'match_leave', matchId: parts[2]!, gameRoleId: parts[3]! };
    }
    if (parts[0] === 'm' && parts[1] === 'o' && parts.length === 3) {
        return { kind: 'match_open_signup_modal', matchId: parts[2]! };
    }
    if (parts[0] === 'c' && parts[1] === 'a' && parts.length === 3) {
        return { kind: 'clan_apply', clanId: parts[2]! };
    }
    return null;
}
