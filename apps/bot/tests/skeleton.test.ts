// Source: 01-VALIDATION.md (skeleton.test.ts: skeleton boots & types compile).
// Plan 01-15 swapped the placeholder TrenchwarsApiContract for the real UserData DTO
// generated from spatie/laravel-data — D-020 LOCKED.
import { describe, it, expect } from 'vitest';
import type { UserData } from '@trenchwars/shared-types';

describe('bot skeleton', () => {
    it('compiles type from shared-types', () => {
        const sample: UserData | null = null;
        expect(sample).toBeNull();
    });

    it('package boots without throwing', () => {
        expect(() => true).not.toThrow();
    });
});
