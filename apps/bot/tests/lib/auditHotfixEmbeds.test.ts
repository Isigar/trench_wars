// Trenchwars bot — v1.0 milestone-audit hotfix embed tests.
//
// Source: .planning/v1.0-MILESTONE-AUDIT.md BLOCKER 1 +
//         .planning/audit-hotfix-bot-dispatcher-SUMMARY.md.
//
// Covers the 3 Phase 7/8/9 embed builders the audit found missing from
// apps/bot/src/lib/embeds.ts. Each builder is exercised on:
//   - happy path (every field set; expected shape lands)
//   - defensive fallbacks (null / empty / unknown values do not throw)
//   - color parsing (hex string vs integer vs malformed)
//   - footer carries the audit identifier (article_id / match_id)
//   - safety clamps for oversize values (T-05-10-02 mitigation)
//
// Idiom matches tests/lib/tournamentEmbeds.test.ts (Phase 6 plan 06-13).

import { describe, expect, it } from 'vitest';

import {
    buildArticleAnnounceEmbed,
    buildMatchResultAnnounceEmbed,
    buildUserDmEmbed,
    type ArticleAnnouncePayload,
    type MatchResultAnnouncePayload,
    type UserDmPayload,
} from '../../src/lib/embeds.js';

// ---------------------------------------------------------------------------
// Fixture helpers — keep them close to the actual web-side payload shape so
// drift between web/bot contracts surfaces fast in CI.
// ---------------------------------------------------------------------------

function makeArticlePayload(
    overrides: Partial<ArticleAnnouncePayload> = {},
): ArticleAnnouncePayload {
    return {
        kind: 'article_announce',
        article_id: '11111111-2222-3333-4444-555555555555',
        article_slug: 'launch-day-recap',
        embeds: [
            {
                title: 'Launch day recap',
                description: 'A short excerpt with highlights from the season opener.',
                url: 'https://example.test/news/launch-day-recap',
                color: '#10B981',
                thumbnail: { url: 'https://example.test/og/launch-day-recap.jpg' },
                fields: [
                    { name: 'Category', value: 'Announcements', inline: true },
                ],
            },
        ],
        ...overrides,
    };
}

function makeMatchResultPayload(
    overrides: Partial<MatchResultAnnouncePayload> = {},
): MatchResultAnnouncePayload {
    return {
        kind: 'match_result_announce',
        match_id: '22222222-3333-4444-5555-666666666666',
        allies_score: 4,
        axis_score: 1,
        winner_clan_name: 'Wolves',
        mvps: [
            { username: 'alpha', kills: 25, deaths: 10 },
            { username: 'bravo', kills: 18, deaths: 9 },
            { username: 'charlie', kills: 14, deaths: 7 },
        ],
        embeds: [
            {
                title: 'Match result',
                color: 0x9b2c3d,
                fields: [
                    { name: 'Score', value: '4 - 1', inline: true },
                    { name: 'Winner', value: 'Wolves', inline: true },
                    {
                        name: 'MVPs',
                        value: 'alpha: K25/D10\nbravo: K18/D9\ncharlie: K14/D7',
                        inline: false,
                    },
                ],
            },
        ],
        ...overrides,
    };
}

function makeUserDmPayload(
    overrides: Partial<UserDmPayload> = {},
): UserDmPayload {
    return {
        recipient_id: '00007777888899990',
        embed_title: 'Match starting soon',
        embed_description: 'Your scrim vs Eagles starts in 15 minutes.',
        cta_url: 'https://example.test/matches/abc-123',
        color_token: 'info',
        ...overrides,
    };
}

// ---------------------------------------------------------------------------
// buildArticleAnnounceEmbed
// ---------------------------------------------------------------------------

describe('buildArticleAnnounceEmbed', () => {
    it('renders title, description, url, thumbnail, and category field', () => {
        const embed = buildArticleAnnounceEmbed(makeArticlePayload());
        const json = embed.toJSON();

        expect(json.title).toBe('Launch day recap');
        expect(json.description).toBe(
            'A short excerpt with highlights from the season opener.',
        );
        expect(json.url).toBe('https://example.test/news/launch-day-recap');
        expect(json.thumbnail?.url).toBe(
            'https://example.test/og/launch-day-recap.jpg',
        );
        const category = (json.fields ?? []).find(
            (f) => f.name === 'Category',
        );
        expect(category?.value).toBe('Announcements');
        expect(category?.inline).toBe(true);
    });

    it('parses #10B981 hex string into the equivalent 24-bit integer color', () => {
        const embed = buildArticleAnnounceEmbed(makeArticlePayload());
        // 0x10B981 = 1095045
        expect(embed.toJSON().color).toBe(0x10b981);
    });

    it('falls back to the CMS-green default color when payload color is malformed', () => {
        const embed = buildArticleAnnounceEmbed(
            makeArticlePayload({
                embeds: [{ title: 't', color: 'not-a-color' }],
            }),
        );
        // Default = COLOR_ARTICLE_ANNOUNCE = 0x10B981
        expect(embed.toJSON().color).toBe(0x10b981);
    });

    it('falls back to article_slug when embed title is empty', () => {
        const embed = buildArticleAnnounceEmbed(
            makeArticlePayload({
                article_slug: 'fallback-slug',
                embeds: [{ title: '', description: 'body' }],
            }),
        );
        expect(embed.toJSON().title).toBe('fallback-slug');
    });

    it('omits thumbnail when payload thumbnail.url is empty', () => {
        const embed = buildArticleAnnounceEmbed(
            makeArticlePayload({
                embeds: [
                    {
                        title: 't',
                        thumbnail: { url: '' },
                    },
                ],
            }),
        );
        expect(embed.toJSON().thumbnail).toBeUndefined();
    });

    it('skips field entries with empty name or value (defensive)', () => {
        const embed = buildArticleAnnounceEmbed(
            makeArticlePayload({
                embeds: [
                    {
                        title: 't',
                        fields: [
                            { name: '', value: 'no name', inline: true },
                            { name: 'Category', value: '', inline: true },
                            { name: 'Tag', value: 'news', inline: true },
                        ],
                    },
                ],
            }),
        );
        const json = embed.toJSON();
        // Only 'Tag' should survive — both malformed entries dropped.
        expect((json.fields ?? []).length).toBe(1);
        expect(json.fields?.[0]?.name).toBe('Tag');
    });

    it('clamps oversize field values to 1024 chars (T-05-10-02)', () => {
        const huge = 'A'.repeat(5000);
        const embed = buildArticleAnnounceEmbed(
            makeArticlePayload({
                embeds: [
                    {
                        title: 't',
                        fields: [{ name: 'Category', value: huge, inline: true }],
                    },
                ],
            }),
        );
        const field = (embed.toJSON().fields ?? []).find(
            (f) => f.name === 'Category',
        );
        expect(field?.value.length).toBe(1024);
    });

    it('footer carries the audit article id', () => {
        const embed = buildArticleAnnounceEmbed(makeArticlePayload());
        expect(embed.toJSON().footer?.text).toBe(
            'Article id: 11111111-2222-3333-4444-555555555555',
        );
    });

    it('renders an empty-embed fallback when embeds[] is empty', () => {
        const embed = buildArticleAnnounceEmbed(
            makeArticlePayload({ embeds: [] }),
        );
        // Title falls back to article_slug; description omitted; footer set.
        const json = embed.toJSON();
        expect(json.title).toBe('launch-day-recap');
        expect(json.description).toBeUndefined();
        expect(json.footer?.text).toBe(
            'Article id: 11111111-2222-3333-4444-555555555555',
        );
    });
});

// ---------------------------------------------------------------------------
// buildMatchResultAnnounceEmbed
// ---------------------------------------------------------------------------

describe('buildMatchResultAnnounceEmbed', () => {
    it('renders the pre-shaped Score / Winner / MVPs fields', () => {
        const embed = buildMatchResultAnnounceEmbed(makeMatchResultPayload());
        const fields = embed.toJSON().fields ?? [];

        const score = fields.find((f) => f.name === 'Score');
        const winner = fields.find((f) => f.name === 'Winner');
        const mvps = fields.find((f) => f.name === 'MVPs');

        expect(score?.value).toBe('4 - 1');
        expect(score?.inline).toBe(true);
        expect(winner?.value).toBe('Wolves');
        expect(winner?.inline).toBe(true);
        // MVPs is multi-line — assert all 3 player names appear.
        expect(mvps?.value).toContain('alpha');
        expect(mvps?.value).toContain('bravo');
        expect(mvps?.value).toContain('charlie');
        expect(mvps?.inline).toBe(false);
    });

    it('uses the integer color from the payload (0x9B2C3D)', () => {
        const embed = buildMatchResultAnnounceEmbed(makeMatchResultPayload());
        expect(embed.toJSON().color).toBe(0x9b2c3d);
    });

    it('falls back to the trench-red default when color is null', () => {
        const embed = buildMatchResultAnnounceEmbed(
            makeMatchResultPayload({
                embeds: [{ title: 'Match result', color: null, fields: [] }],
            }),
        );
        expect(embed.toJSON().color).toBe(0x9b2c3d);
    });

    it('falls back to "Match result" title when embeds[0].title is empty', () => {
        const embed = buildMatchResultAnnounceEmbed(
            makeMatchResultPayload({
                embeds: [{ title: '', fields: [] }],
            }),
        );
        expect(embed.toJSON().title).toBe('Match result');
    });

    it('footer carries the audit match id', () => {
        const embed = buildMatchResultAnnounceEmbed(makeMatchResultPayload());
        expect(embed.toJSON().footer?.text).toBe(
            'Match id: 22222222-3333-4444-5555-666666666666',
        );
    });

    it('does not leak Steam IDs (T-08-12-01) — MVPs only carry username/K/D', () => {
        // Sanity: the payload shape has no steam_id_64 field. We assert by
        // type — adding one would be a compile error. Runtime check that the
        // rendered MVPs field never contains a 17-digit numeric token.
        const embed = buildMatchResultAnnounceEmbed(makeMatchResultPayload());
        const mvps = (embed.toJSON().fields ?? []).find(
            (f) => f.name === 'MVPs',
        );
        expect(mvps?.value).not.toMatch(/\b\d{17}\b/);
    });
});

// ---------------------------------------------------------------------------
// buildUserDmEmbed
// ---------------------------------------------------------------------------

describe('buildUserDmEmbed', () => {
    it('renders title, description, url, and info color from color_token', () => {
        const embed = buildUserDmEmbed(makeUserDmPayload());
        const json = embed.toJSON();

        expect(json.title).toBe('Match starting soon');
        expect(json.description).toBe(
            'Your scrim vs Eagles starts in 15 minutes.',
        );
        expect(json.url).toBe('https://example.test/matches/abc-123');
        // info → 0x3B82F6 (blue-500)
        expect(json.color).toBe(0x3b82f6);
    });

    it('maps each known color_token to its 24-bit color', () => {
        const expectations: Array<[string, number]> = [
            ['info', 0x3b82f6],
            ['success', 0x10b981],
            ['warning', 0xf59e0b],
            ['danger', 0xef4444],
            ['neutral', 0x666666],
        ];
        for (const [token, expected] of expectations) {
            const embed = buildUserDmEmbed(
                makeUserDmPayload({ color_token: token }),
            );
            expect(embed.toJSON().color).toBe(expected);
        }
    });

    it('falls back to neutral grey when color_token is unknown or missing', () => {
        const unknown = buildUserDmEmbed(
            makeUserDmPayload({ color_token: 'plaid' }),
        );
        const missing = buildUserDmEmbed(
            makeUserDmPayload({ color_token: null }),
        );
        expect(unknown.toJSON().color).toBe(0x666666);
        expect(missing.toJSON().color).toBe(0x666666);
    });

    it('falls back to "Notification" title when embed_title is empty', () => {
        const embed = buildUserDmEmbed(
            makeUserDmPayload({ embed_title: '' }),
        );
        expect(embed.toJSON().title).toBe('Notification');
    });

    it('omits description when embed_description is null', () => {
        const embed = buildUserDmEmbed(
            makeUserDmPayload({ embed_description: null }),
        );
        expect(embed.toJSON().description).toBeUndefined();
    });

    it('omits URL when cta_url is empty', () => {
        const embed = buildUserDmEmbed(
            makeUserDmPayload({ cta_url: '' }),
        );
        expect(embed.toJSON().url).toBeUndefined();
    });

    it('clamps oversize description to 2000 chars', () => {
        const huge = 'B'.repeat(5000);
        const embed = buildUserDmEmbed(
            makeUserDmPayload({ embed_description: huge }),
        );
        expect(embed.toJSON().description?.length).toBe(2000);
    });

    it('does NOT include a footer (DMs are recipient-personal — no audit id)', () => {
        const embed = buildUserDmEmbed(makeUserDmPayload());
        expect(embed.toJSON().footer).toBeUndefined();
    });
});
