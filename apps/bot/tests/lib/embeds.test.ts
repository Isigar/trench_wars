// Trenchwars bot — embed builders tests (Wave 9 GREEN flip).
//
// Source: .planning/phases/05-discord-bot-v1/05-10-PLAN.md task 1.
// Replaces the Wave 0 RED stub. Asserts SC-1 / SC-3:
//
//   matchCard
//     - color matches statusColor(m.status)
//     - title fallback when m.title.en is null
//     - <t:UNIX_TS:F> Discord timestamp tag when scheduled_at is set
//     - includes Sign-up button when status='open'; no buttons otherwise
//     - footer carries `Match id: <uuid>`
//     - snapshot of toJSON shape (color int, fields array, footer)
//
//   clanCard
//     - title from c.name, footer from c.id, includes Tags field when present
//
//   profileCard
//     - skips null/undefined fields (privacy gate already collapsed them
//       upstream)

import { describe, expect, it } from 'vitest';

import { matchCard, clanCard, profileCard } from '../../src/lib/embeds.js';
import { statusColor } from '../../src/lib/colors.js';
import type {
    ClanData,
    PublicMatchData,
    PublicPlayerData,
} from '../../src/types/apiContracts.js';

const MATCH_UUID = '01234567-89ab-cdef-0123-456789abcdef';
const HOST_CLAN_UUID = '11111111-2222-3333-4444-555555555555';
const TYPE_UUID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

function makeMatch(overrides: Partial<PublicMatchData> = {}): PublicMatchData {
    return {
        id: MATCH_UUID,
        game_match_type_id: TYPE_UUID,
        title: { en: 'Friendly skirmish' },
        description: { en: 'Casual evening match' },
        scheduled_at: '2026-06-15T20:00:00Z',
        status: 'open',
        is_public: true,
        host_clan_id: HOST_CLAN_UUID,
        ...overrides,
    };
}

describe('matchCard', () => {
    it('returns embeds[0] with color matching statusColor(m.status)', () => {
        const { embeds } = matchCard(makeMatch({ status: 'open' }));
        const json = embeds[0]!.toJSON();
        expect(json.color).toBe(statusColor('open'));
    });

    it('title falls back to "Match <id>" when m.title.en is null', () => {
        const { embeds } = matchCard(makeMatch({ title: null }));
        const json = embeds[0]!.toJSON();
        expect(json.title).toBe(`Match ${MATCH_UUID}`);
    });

    it('uses m.title.en when present', () => {
        const { embeds } = matchCard(makeMatch({ title: { en: 'Big game' } }));
        const json = embeds[0]!.toJSON();
        expect(json.title).toBe('Big game');
    });

    it('renders <t:UNIX_TS:F> Discord timestamp tag for scheduled_at', () => {
        const m = makeMatch({ scheduled_at: '2026-06-15T20:00:00Z' });
        const expectedTs = Math.floor(new Date(m.scheduled_at).getTime() / 1000);
        const { embeds } = matchCard(m);
        const json = embeds[0]!.toJSON();
        const scheduledField = (json.fields ?? []).find(
            (f) => f.name === 'Scheduled',
        );
        expect(scheduledField).toBeDefined();
        expect(scheduledField?.value).toBe(`<t:${expectedTs}:F>`);
    });

    it('includes Sign up button when status=open', () => {
        const { components } = matchCard(makeMatch({ status: 'open' }));
        expect(components).toHaveLength(1);
        const row = components[0]!.toJSON();
        expect(row.components).toHaveLength(1);
        const btn = row.components[0] as {
            label: string;
            custom_id: string;
            style: number;
        };
        expect(btn.label).toBe('Sign up');
        expect(btn.custom_id).toBe(`m:o:${MATCH_UUID}`);
    });

    it('emits no buttons when status=cancelled', () => {
        const { components } = matchCard(makeMatch({ status: 'cancelled' }));
        expect(components).toHaveLength(0);
    });

    it('emits no buttons when status=locked', () => {
        const { components } = matchCard(makeMatch({ status: 'locked' }));
        expect(components).toHaveLength(0);
    });

    it('emits no buttons when status=played', () => {
        const { components } = matchCard(makeMatch({ status: 'played' }));
        expect(components).toHaveLength(0);
    });

    it('omits the Host clan field when host_clan_id is null', () => {
        const { embeds } = matchCard(makeMatch({ host_clan_id: null }));
        const json = embeds[0]!.toJSON();
        const hostField = (json.fields ?? []).find((f) => f.name === 'Host clan');
        expect(hostField).toBeUndefined();
    });

    it('footer contains the match id', () => {
        const { embeds } = matchCard(makeMatch());
        const json = embeds[0]!.toJSON();
        expect(json.footer?.text).toBe(`Match id: ${MATCH_UUID}`);
    });

    it('clamps description to 2000 chars', () => {
        const longDesc = 'x'.repeat(5000);
        const { embeds } = matchCard(
            makeMatch({ description: { en: longDesc } }),
        );
        const json = embeds[0]!.toJSON();
        expect((json.description ?? '').length).toBeLessThanOrEqual(2000);
    });

    it('toJSON shape carries color, fields, footer (snapshot)', () => {
        const { embeds } = matchCard(makeMatch({ status: 'open' }));
        const json = embeds[0]!.toJSON();
        expect(typeof json.color).toBe('number');
        expect(Array.isArray(json.fields)).toBe(true);
        expect((json.fields ?? []).length).toBeGreaterThanOrEqual(3);
        expect(json.footer?.text).toMatch(/^Match id: /);
        expect(json.title).toBeDefined();
    });
});

describe('clanCard', () => {
    const clan: ClanData = {
        id: 'c-uuid',
        slug: 'redwave',
        tag: 'RW',
        name: 'Red Wave',
        description: { en: 'A clan of veteran tankers.' },
        country_code: 'DE',
        status: 'active',
        discord_role_id: null,
        tags: [
            { id: 't1', slug: 'eu', label: { en: 'Europe' }, color: '#1133aa' },
        ],
        active_member_count: 12,
    };

    it('renders title from c.name with footer containing c.id', () => {
        const { embeds } = clanCard(clan);
        const json = embeds[0]!.toJSON();
        expect(json.title).toBe('Red Wave');
        expect(json.footer?.text).toBe('Clan id: c-uuid');
    });

    it('renders Tags field when c.tags is non-empty', () => {
        const { embeds } = clanCard(clan);
        const json = embeds[0]!.toJSON();
        const tagsField = (json.fields ?? []).find((f) => f.name === 'Tags');
        expect(tagsField).toBeDefined();
        expect(tagsField?.value).toContain('`eu`');
    });

    it('omits Tags field when c.tags is empty', () => {
        const noTagClan = { ...clan, tags: [] };
        const { embeds } = clanCard(noTagClan);
        const json = embeds[0]!.toJSON();
        const tagsField = (json.fields ?? []).find((f) => f.name === 'Tags');
        expect(tagsField).toBeUndefined();
    });
});

describe('profileCard', () => {
    function makePlayer(
        overrides: Partial<PublicPlayerData> = {},
    ): PublicPlayerData {
        return {
            id: 'p-uuid',
            slug: 'ace-pilot',
            displayName: 'AcePilot',
            avatarUrl: 'https://cdn.example/avatars/p-uuid.webp',
            isOwnProfile: false,
            countryCode: 'CZ',
            discordTag: undefined,
            bio: undefined,
            currentClan: undefined,
            clanHistory: undefined,
            matchHistory: undefined,
            stats: undefined,
            ...overrides,
        };
    }

    it('renders title from p.slug with footer containing p.id', () => {
        const { embeds } = profileCard(makePlayer());
        const json = embeds[0]!.toJSON();
        expect(json.title).toBe('Player: ace-pilot');
        expect(json.footer?.text).toBe('Player id: p-uuid');
    });

    it('hides discordTag field when undefined (privacy gate)', () => {
        const { embeds } = profileCard(makePlayer({ discordTag: undefined }));
        const json = embeds[0]!.toJSON();
        const f = (json.fields ?? []).find((x) => x.name === 'Discord');
        expect(f).toBeUndefined();
    });

    it('hides discordTag field when null (privacy gate)', () => {
        const { embeds } = profileCard(makePlayer({ discordTag: null }));
        const json = embeds[0]!.toJSON();
        const f = (json.fields ?? []).find((x) => x.name === 'Discord');
        expect(f).toBeUndefined();
    });

    it('shows discordTag field when set', () => {
        const { embeds } = profileCard(
            makePlayer({ discordTag: 'AcePilot#1234' }),
        );
        const json = embeds[0]!.toJSON();
        const f = (json.fields ?? []).find((x) => x.name === 'Discord');
        expect(f?.value).toBe('AcePilot#1234');
    });

    it('hides bio field when undefined', () => {
        const { embeds } = profileCard(makePlayer({ bio: undefined }));
        const json = embeds[0]!.toJSON();
        const f = (json.fields ?? []).find((x) => x.name === 'Bio');
        expect(f).toBeUndefined();
    });
});
