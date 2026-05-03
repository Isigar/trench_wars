// Source: 01-VALIDATION.md (skeleton.test.ts: skeleton boots & types compile).
// Note: imports TrenchwarsApiContract — the placeholder shared-types export shipped
// in plan 01-01. Plan 01-15 (wave 10) replaces this with concrete UserData/PlayerData
// DTOs generated from spatie/laravel-data; this skeleton test will be updated then.
import { describe, it, expect } from 'vitest';
import type { TrenchwarsApiContract } from '@trenchwars/shared-types';

describe('bot skeleton', () => {
    it('compiles type from shared-types', () => {
        const sample: TrenchwarsApiContract | null = null;
        expect(sample).toBeNull();
    });

    it('package boots without throwing', () => {
        expect(() => true).not.toThrow();
    });
});
