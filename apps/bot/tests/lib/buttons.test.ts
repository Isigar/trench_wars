// Trenchwars bot — paginationButtons ActionRow factory tests (plan 12-02).
//
// Verifies: Prev/Next buttons with correct customIds via encodeButtonId,
// Prev disabled at page<=1, Next disabled at page>=lastPage.

import { ButtonStyle } from 'discord.js';
import { describe, expect, it } from 'vitest';

import { paginationButtons } from '../../src/lib/buttons.js';

describe('paginationButtons (12-02)', () => {
    it('returns an ActionRow with two buttons for mid-range page', () => {
        const row = paginationButtons('match', 2, 5);
        const components = row.components;
        expect(components).toHaveLength(2);
    });

    it('Prev customId is pg:m:1 and Next customId is pg:m:3 for page=2, lastPage=5', () => {
        const row = paginationButtons('match', 2, 5);
        const [prev, next] = row.components as { data: { custom_id: string; disabled?: boolean } }[];
        expect(prev!.data.custom_id).toBe('pg:m:1');
        expect(next!.data.custom_id).toBe('pg:m:3');
    });

    it('neither button is disabled for mid-range page (page=2, lastPage=5)', () => {
        const row = paginationButtons('match', 2, 5);
        const [prev, next] = row.components as { data: { disabled?: boolean } }[];
        expect(prev!.data.disabled).toBeFalsy();
        expect(next!.data.disabled).toBeFalsy();
    });

    it('Prev is disabled when page=1', () => {
        const row = paginationButtons('match', 1, 5);
        const [prev, next] = row.components as { data: { disabled?: boolean } }[];
        expect(prev!.data.disabled).toBe(true);
        expect(next!.data.disabled).toBeFalsy();
    });

    it('Next is disabled when page=lastPage', () => {
        const row = paginationButtons('match', 5, 5);
        const [prev, next] = row.components as { data: { disabled?: boolean } }[];
        expect(prev!.data.disabled).toBeFalsy();
        expect(next!.data.disabled).toBe(true);
    });

    it('uses pg:c: prefix for clan listType', () => {
        const row = paginationButtons('clan', 2, 3);
        const [prev, next] = row.components as { data: { custom_id: string } }[];
        expect(prev!.data.custom_id).toBe('pg:c:1');
        expect(next!.data.custom_id).toBe('pg:c:3');
    });

    it('Prev button has label "Prev" and Secondary style', () => {
        const row = paginationButtons('match', 2, 5);
        const [prev] = row.components as { data: { label: string; style: number } }[];
        expect(prev!.data.label).toBe('Prev');
        expect(prev!.data.style).toBe(ButtonStyle.Secondary);
    });

    it('Next button has label "Next" and Secondary style', () => {
        const row = paginationButtons('match', 2, 5);
        const [, next] = row.components as { data: { label: string; style: number } }[];
        expect(next!.data.label).toBe('Next');
        expect(next!.data.style).toBe(ButtonStyle.Secondary);
    });
});
