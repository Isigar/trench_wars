// Trenchwars Discord bot — Phase 5 fills this in. Plan 01-foundations only ships the skeleton.
//
// Plan 01-15 swapped the placeholder TrenchwarsApiContract type for the real UserData
// DTO emitted by spatie/laravel-typescript-transformer (D-020). The skeleton just touches
// the import so tsc --noEmit doesn't trip on the unused-import sentinel.
import type { UserData } from "@trenchwars/shared-types";

const _phase1Marker: UserData | null = null;
void _phase1Marker;

console.log("[trenchwars-bot] skeleton boot — Phase 5 implements discord.js integration");
