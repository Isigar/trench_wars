<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/08-rcon-automation/08-03-PLAN.md — Rule 1 deviation
 * (auto-fix bug discovered during plan 08-03 execution).
 *
 * Plan 08-02 created `match_servers.credentials_encrypted` as `jsonb`, but the
 * plan 08-03 mandate is that the model casts it as Laravel's `encrypted:array`.
 * Laravel's `encrypted:array` cast serialises the plaintext array, runs it
 * through `Crypt::encryptString()`, and stores the resulting base64-of-JSON
 * envelope as a RAW STRING — not as JSON content. Postgres `jsonb` rejects
 * this on INSERT with `SQLSTATE 22P02: invalid input syntax for type json`
 * because the ciphertext envelope is unparseable JSON.
 *
 * Fix: ALTER the column to `text`. This preserves the plan-mandated cast
 * (`encrypted:array`) and aligns with the canonical Laravel pattern (encrypted
 * casts on `text`/`varchar` columns). No existing data to migrate — plan 08-02
 * landed days ago and the column is unused outside of test fixtures (this plan
 * is the first to read/write from it).
 *
 * down(): revert to jsonb. Idempotency: the USING clause coerces existing rows
 * (if any) back to JSON via parse; an envelope-shaped row would fail the cast,
 * which is the correct behaviour for a "rewind to broken state" rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE match_servers ALTER COLUMN credentials_encrypted TYPE text USING credentials_encrypted::text;');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE match_servers ALTER COLUMN credentials_encrypted TYPE jsonb USING credentials_encrypted::jsonb;');
    }
};
