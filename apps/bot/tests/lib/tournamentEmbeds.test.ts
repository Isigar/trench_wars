// Trenchwars bot — tournament + bracket-result embed tests (plan 06-13 task 2).
//
// Source: .planning/phases/06-tournaments-brackets/06-13-PLAN.md <interfaces>.
// Asserts:
//
//   buildTournamentAnnounceEmbed
//     - happy path: title + format + status + max_participants + starts_at
//       fields all land in the embed
//     - locale-aware title falls back to title.en then to 'Tournament'
//     - URL composes from WEB_URL env var (fallback 'http://localhost') +
//       tournament slug; falls back to '/tournaments' when slug is empty
//     - missing fields default to '—' / sensible noop instead of throwing
//     - footer carries tournament id when set; omitted when empty
//
//   buildBracketResultEmbed
//     - title is `Round N — Match P`
//     - description shows `<winner> defeated <loser>` when winner_clan_name
//       is set; 'Result pending' otherwise
//     - URL composes via tournament slug
//     - tournament_title is added as a field when present
//     - footer carries bracket id when set
//
// Analog: tests/lib/embeds.test.ts (Phase 5 plan 05-10 canonical idiom).

import { describe, expect, it } from 'vitest';

import {
    buildBracketResultEmbed,
    buildTournamentAnnounceEmbed,
    type BracketResultPayload,
    type TournamentAnnouncePayload,
} from '../../src/lib/embeds.js';

function makeTournamentPayload(
    overrides: Partial<TournamentAnnouncePayload> = {},
): TournamentAnnouncePayload {
    return {
        kind: 'tournament_announce',
        tournament_id: '00000000-1111-2222-3333-444444444444',
        tournament_slug: 'open-2026',
        title: { en: 'Open 2026' },
        format: 'single_elimination',
        status: 'registering',
        starts_at: '2026-06-01T19:00:00Z',
        ends_at: null,
        organiser_user_id: '11111111-2222-3333-4444-555555555555',
        max_participants: 16,
        is_public: true,
        ...overrides,
    };
}

function makeBracketResultPayload(
    overrides: Partial<BracketResultPayload> = {},
): BracketResultPayload {
    return {
        kind: 'bracket_result_announce',
        tournament_id: '00000000-1111-2222-3333-444444444444',
        tournament_slug: 'open-2026',
        tournament_title: 'Open 2026',
        stage_id: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        stage_type: 'elim',
        bracket_id: 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
        round_number: 1,
        position: 2,
        winner_participant_id: 'cccccccc-dddd-eeee-ffff-000000000000',
        winner_clan_id: 'dddddddd-eeee-ffff-0000-111111111111',
        winner_clan_name: 'Wolves',
        participant_a_clan_name: 'Wolves',
        participant_b_clan_name: 'Eagles',
        ...overrides,
    };
}

describe('buildTournamentAnnounceEmbed', () => {
    it('renders title + URL + 4 fields for a fully-populated payload', () => {
        const embed = buildTournamentAnnounceEmbed(makeTournamentPayload());
        const json = embed.toJSON();

        expect(json.title).toBe('Open 2026');
        // URL composes from WEB_URL fallback 'http://localhost' + slug suffix.
        expect(json.url).toContain('/tournaments/open-2026');
        // 3 base fields (Format, Status, Max participants) + Starts at = 4.
        expect(json.fields ?? []).toHaveLength(4);
    });

    it('renders format + status + max_participants as inline fields', () => {
        const embed = buildTournamentAnnounceEmbed(makeTournamentPayload());
        const json = embed.toJSON();
        const format = (json.fields ?? []).find((f) => f.name === 'Format');
        const status = (json.fields ?? []).find((f) => f.name === 'Status');
        const max = (json.fields ?? []).find(
            (f) => f.name === 'Max participants',
        );

        expect(format?.value).toBe('single_elimination');
        expect(status?.value).toBe('registering');
        expect(max?.value).toBe('16');
        expect(format?.inline).toBe(true);
        expect(status?.inline).toBe(true);
        expect(max?.inline).toBe(true);
    });

    it('renders Starts at as <t:UNIX_TS:F> Discord timestamp tag', () => {
        const payload = makeTournamentPayload({
            starts_at: '2026-06-01T19:00:00Z',
        });
        const expectedTs = Math.floor(
            new Date(payload.starts_at!).getTime() / 1000,
        );
        const embed = buildTournamentAnnounceEmbed(payload);
        const json = embed.toJSON();
        const starts = (json.fields ?? []).find((f) => f.name === 'Starts at');
        expect(starts?.value).toBe(`<t:${expectedTs}:F>`);
    });

    it('omits Starts at field when starts_at is null', () => {
        const embed = buildTournamentAnnounceEmbed(
            makeTournamentPayload({ starts_at: null }),
        );
        const json = embed.toJSON();
        const starts = (json.fields ?? []).find((f) => f.name === 'Starts at');
        expect(starts).toBeUndefined();
    });

    it('falls back to "Tournament" title when title map is empty', () => {
        const embed = buildTournamentAnnounceEmbed(
            makeTournamentPayload({ title: {} }),
        );
        expect(embed.toJSON().title).toBe('Tournament');
    });

    it('falls back to title.en when requested locale is missing', () => {
        const embed = buildTournamentAnnounceEmbed(
            makeTournamentPayload({ title: { en: 'Open EN' } }),
            'cs',
        );
        expect(embed.toJSON().title).toBe('Open EN');
    });

    it('honours the requested locale when present in title map', () => {
        const embed = buildTournamentAnnounceEmbed(
            makeTournamentPayload({
                title: { en: 'Open EN', cs: 'Otevreny 2026' },
            }),
            'cs',
        );
        expect(embed.toJSON().title).toBe('Otevreny 2026');
    });

    it('renders "—" for null format / status / max_participants (defensive)', () => {
        const embed = buildTournamentAnnounceEmbed(
            makeTournamentPayload({
                format: null,
                status: null,
                max_participants: null,
            }),
        );
        const json = embed.toJSON();
        const format = (json.fields ?? []).find((f) => f.name === 'Format');
        const status = (json.fields ?? []).find((f) => f.name === 'Status');
        const max = (json.fields ?? []).find(
            (f) => f.name === 'Max participants',
        );
        expect(format?.value).toBe('—');
        expect(status?.value).toBe('—');
        expect(max?.value).toBe('—');
    });

    it('uses /tournaments base URL when tournament_slug is empty', () => {
        const embed = buildTournamentAnnounceEmbed(
            makeTournamentPayload({ tournament_slug: '' }),
        );
        const json = embed.toJSON();
        // Trailing slash variant is acceptable; assert /tournaments is present
        // and no per-slug suffix landed.
        expect(json.url).toBeDefined();
        expect(json.url).toMatch(/\/tournaments$/);
    });

    it('footer carries the tournament id when set', () => {
        const embed = buildTournamentAnnounceEmbed(makeTournamentPayload());
        expect(embed.toJSON().footer?.text).toBe(
            'Tournament id: 00000000-1111-2222-3333-444444444444',
        );
    });

    it('omits footer when tournament_id is null', () => {
        const embed = buildTournamentAnnounceEmbed(
            makeTournamentPayload({ tournament_id: null }),
        );
        expect(embed.toJSON().footer).toBeUndefined();
    });

    it('ignores malformed starts_at strings without throwing', () => {
        const embed = buildTournamentAnnounceEmbed(
            makeTournamentPayload({ starts_at: 'not-a-date' }),
        );
        const json = embed.toJSON();
        const starts = (json.fields ?? []).find((f) => f.name === 'Starts at');
        expect(starts).toBeUndefined();
    });
});

describe('buildBracketResultEmbed', () => {
    it('renders title as Round N — Match P', () => {
        const embed = buildBracketResultEmbed(makeBracketResultPayload());
        const json = embed.toJSON();
        expect(json.title).toBe('Round 1 — Match 2');
    });

    it('renders description as "<winner> defeated <loser>" when winner_clan_name is set', () => {
        const embed = buildBracketResultEmbed(makeBracketResultPayload());
        const json = embed.toJSON();
        // Wolves were participant_a AND winner; loser is Eagles.
        expect(json.description).toContain('Wolves');
        expect(json.description).toContain('defeated');
        expect(json.description).toContain('Eagles');
    });

    it('picks the opposite side when winner equals participant_b', () => {
        const embed = buildBracketResultEmbed(
            makeBracketResultPayload({
                participant_a_clan_name: 'Eagles',
                participant_b_clan_name: 'Wolves',
                winner_clan_name: 'Wolves',
            }),
        );
        const desc = embed.toJSON().description ?? '';
        // Loser should be Eagles (the side != winner).
        expect(desc.startsWith('**Wolves** defeated Eagles')).toBe(true);
    });

    it('renders "Result pending" description when winner_clan_name is null', () => {
        const embed = buildBracketResultEmbed(
            makeBracketResultPayload({ winner_clan_name: null }),
        );
        expect(embed.toJSON().description).toBe('Result pending');
    });

    it('URL composes via tournament slug', () => {
        const embed = buildBracketResultEmbed(makeBracketResultPayload());
        expect(embed.toJSON().url).toContain('/tournaments/open-2026');
    });

    it('renders Tournament field when tournament_title is set', () => {
        const embed = buildBracketResultEmbed(makeBracketResultPayload());
        const json = embed.toJSON();
        const tField = (json.fields ?? []).find(
            (f) => f.name === 'Tournament',
        );
        expect(tField?.value).toBe('Open 2026');
    });

    it('omits Tournament field when tournament_title is null', () => {
        const embed = buildBracketResultEmbed(
            makeBracketResultPayload({ tournament_title: null }),
        );
        const json = embed.toJSON();
        const tField = (json.fields ?? []).find(
            (f) => f.name === 'Tournament',
        );
        expect(tField).toBeUndefined();
    });

    it('footer carries the bracket id when set', () => {
        const embed = buildBracketResultEmbed(makeBracketResultPayload());
        expect(embed.toJSON().footer?.text).toBe(
            'Bracket id: bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
        );
    });

    it('omits footer when bracket_id is null', () => {
        const embed = buildBracketResultEmbed(
            makeBracketResultPayload({ bracket_id: null }),
        );
        expect(embed.toJSON().footer).toBeUndefined();
    });

    it('defaults round/position to 0 when null in payload', () => {
        const embed = buildBracketResultEmbed(
            makeBracketResultPayload({
                round_number: null,
                position: null,
            }),
        );
        expect(embed.toJSON().title).toBe('Round 0 — Match 0');
    });
});
