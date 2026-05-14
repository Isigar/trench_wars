<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/07-cms/07-02-PLAN.md task 1(d).
 *         .planning/phases/07-cms/07-RESEARCH.md Pattern 3 (verbatim trigger idiom).
 *
 * Full-text search substrate for Phase 7 SearchService (plan 07-08):
 *   - 3 tsvector columns (articles.search_vector, clans.search_vector,
 *     players.search_vector).
 *   - 3 plpgsql trigger functions populating the column from per-table source
 *     fields (title/excerpt/slug for articles; name/description/slug for clans;
 *     username/display_name/slug for players).
 *   - 3 BEFORE INSERT OR UPDATE FOR EACH ROW triggers — survives raw SQL writes
 *     (seeders, Filament bulk actions, factories) — Pitfall 3 mitigation.
 *   - 3 GIN indexes for plainto_tsquery() match performance.
 *   - 3 backfill UPDATE statements run inside this migration to populate
 *     search_vector for any pre-existing rows (Pitfall 9 — migration ordering:
 *     this timestamp 120300 comes AFTER articles (120100) so the trigger can
 *     reference articles columns at CREATE FUNCTION compile time).
 *
 * Text-search config 'simple' (NOT 'english') — RESEARCH A2:
 *   - English-only at launch (D-013); no stemming acceptable for v1 editorial
 *     corpus.
 *   - Switch to 'pg_catalog.english' if owner reports relevance gaps (single-
 *     line migration follow-up).
 *
 * Indexed columns per table (D-018 enforcement — public fields only):
 *   - articles: title->>'en' + excerpt->>'en' + slug
 *     (body is intentionally NOT indexed — author-controlled HTML/JSON; bulky;
 *     plan 07-08 SearchService is for headings, not full-text reading.)
 *   - clans: name (text) + description->>'en' + slug
 *   - players: username (from users.username via JOIN-less denormalisation —
 *     stored on the trigger? No: trigger fires per-row on players; users.username
 *     is not on the players row. We index display_name + slug instead. Players'
 *     real_name + discord_tag are private-only (D-018) and intentionally NOT
 *     indexed — they aren't searchable in the first place; PlayerPrivacyGate at
 *     query time is the second filter.)
 *
 * Threat refs:
 *   T-07-02-03 (Information Disclosure): trigger function indexes ONLY public
 *     fields; private-only fields stay out of the index entirely (defence-in-
 *     depth alongside PlayerPrivacyGate in plan 07-08).
 *   T-07-02-04 (DOS via unbounded body): body NOT indexed in v1; if corpus
 *     grows past 50KB/article average, switch to a separate body_search_vector
 *     column with materialised view or trim policy.
 *   T-07-02-06 (Mass-insert bypass — Pitfall 3): BEFORE INSERT OR UPDATE trigger
 *     fires regardless of writer (Eloquent / raw SQL / seeders).
 *
 * Down: drops triggers + functions + indexes + columns in reverse order.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── articles ──────────────────────────────────────────────────────
        DB::statement('ALTER TABLE articles ADD COLUMN search_vector tsvector;');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION articles_search_vector_trigger() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector :=
                    to_tsvector('simple',
                        coalesce(NEW.title->>'en', '') || ' ' ||
                        coalesce(NEW.excerpt->>'en', '') || ' ' ||
                        coalesce(NEW.slug, ''));
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql;
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER articles_search_vector_update
            BEFORE INSERT OR UPDATE ON articles
            FOR EACH ROW EXECUTE FUNCTION articles_search_vector_trigger();
        SQL);
        DB::statement('CREATE INDEX articles_search_vector_idx ON articles USING GIN (search_vector);');
        DB::statement(<<<'SQL'
            UPDATE articles SET search_vector =
                to_tsvector('simple',
                    coalesce(title->>'en', '') || ' ' ||
                    coalesce(excerpt->>'en', '') || ' ' ||
                    coalesce(slug, ''));
        SQL);

        // ─── clans ─────────────────────────────────────────────────────────
        // Note: clans.name is plain text (not JSONB) per Phase 2 schema;
        // clans.description is JSONB (translatable).
        DB::statement('ALTER TABLE clans ADD COLUMN search_vector tsvector;');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION clans_search_vector_trigger() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector :=
                    to_tsvector('simple',
                        coalesce(NEW.name, '') || ' ' ||
                        coalesce(NEW.tag, '') || ' ' ||
                        coalesce(NEW.description->>'en', '') || ' ' ||
                        coalesce(NEW.slug, ''));
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql;
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER clans_search_vector_update
            BEFORE INSERT OR UPDATE ON clans
            FOR EACH ROW EXECUTE FUNCTION clans_search_vector_trigger();
        SQL);
        DB::statement('CREATE INDEX clans_search_vector_idx ON clans USING GIN (search_vector);');
        DB::statement(<<<'SQL'
            UPDATE clans SET search_vector =
                to_tsvector('simple',
                    coalesce(name, '') || ' ' ||
                    coalesce(tag, '') || ' ' ||
                    coalesce(description->>'en', '') || ' ' ||
                    coalesce(slug, ''));
        SQL);

        // ─── players ───────────────────────────────────────────────────────
        // D-018: only public-by-default fields (display_name + slug) are
        // indexed. real_name + discord_tag are private-tiered and stay out
        // of the FTS index (search shouldn't return rows on those fields).
        DB::statement('ALTER TABLE players ADD COLUMN search_vector tsvector;');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION players_search_vector_trigger() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector :=
                    to_tsvector('simple',
                        coalesce(NEW.display_name, '') || ' ' ||
                        coalesce(NEW.slug, ''));
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql;
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER players_search_vector_update
            BEFORE INSERT OR UPDATE ON players
            FOR EACH ROW EXECUTE FUNCTION players_search_vector_trigger();
        SQL);
        DB::statement('CREATE INDEX players_search_vector_idx ON players USING GIN (search_vector);');
        DB::statement(<<<'SQL'
            UPDATE players SET search_vector =
                to_tsvector('simple',
                    coalesce(display_name, '') || ' ' ||
                    coalesce(slug, ''));
        SQL);
    }

    public function down(): void
    {
        // players ──────────────────────────────────────────────────────────
        DB::statement('DROP TRIGGER IF EXISTS players_search_vector_update ON players;');
        DB::statement('DROP FUNCTION IF EXISTS players_search_vector_trigger();');
        DB::statement('DROP INDEX IF EXISTS players_search_vector_idx;');
        DB::statement('ALTER TABLE players DROP COLUMN IF EXISTS search_vector;');

        // clans ────────────────────────────────────────────────────────────
        DB::statement('DROP TRIGGER IF EXISTS clans_search_vector_update ON clans;');
        DB::statement('DROP FUNCTION IF EXISTS clans_search_vector_trigger();');
        DB::statement('DROP INDEX IF EXISTS clans_search_vector_idx;');
        DB::statement('ALTER TABLE clans DROP COLUMN IF EXISTS search_vector;');

        // articles ─────────────────────────────────────────────────────────
        DB::statement('DROP TRIGGER IF EXISTS articles_search_vector_update ON articles;');
        DB::statement('DROP FUNCTION IF EXISTS articles_search_vector_trigger();');
        DB::statement('DROP INDEX IF EXISTS articles_search_vector_idx;');
        DB::statement('ALTER TABLE articles DROP COLUMN IF EXISTS search_vector;');
    }
};
