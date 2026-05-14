// Wave 0 skeleton — Phase 8 plan 08-01 task 1.
// Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 1 behaviour list.
//
// Plan 01-15 swapped the placeholder TrenchwarsApiContract type for the real UserData
// DTO emitted by spatie/laravel-typescript-transformer (D-020). Phase 8 plan 08-11
// replaces this placeholder with the full booking-poller + CRCON session lifecycle —
// for Wave 0 this remains a console.log so the container's `node dist/index.js`
// command boots cleanly under existing Phase 1 healthcheck (`pgrep node`).
import type { UserData } from '@trenchwars/shared-types';

const _phase1Marker: UserData | null = null;
void _phase1Marker;

console.log('rcon-worker booted');
