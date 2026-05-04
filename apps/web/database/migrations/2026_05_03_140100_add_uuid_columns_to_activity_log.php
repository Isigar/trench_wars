<?php

declare(strict_types=1);

/*
| Source: 01-14-PLAN.md task 1 + 01-RESEARCH.md HasUuids pattern.
|
| Spatie's published migration uses unsignedBigInteger for subject_id + causer_id
| (Laravel `nullableMorphs` default). Our HasUuidPrimaryKey models (D-002) use
| uuid PKs, so we ALTER the columns to uuid AFTER the create migration runs.
|
| This is a safe Postgres ALTER because the table is empty at install time. If
| there are existing rows, run a manual data migration first (none in P1).
|
| Postgres requires dropping indexes that reference a column before altering its
| type, then re-creating them — see psql `\d activity_log` for index names.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hard-fail if the table already has rows. The `USING NULL`-based cast
        // we used previously would discard every existing subject_id / causer_id
        // value, silently nuking the audit trail. Refusing to proceed forces an
        // operator to author a manual data migration when one is needed.
        $rowCount = (int) DB::scalar('SELECT COUNT(*) FROM activity_log');
        if ($rowCount > 0) {
            throw new RuntimeException(
                "activity_log has {$rowCount} rows — UUID column conversion would destroy data. " .
                'Author a manual data migration first (cast existing bigint IDs to uuid via a ' .
                'staging column) and re-run this migration with the table emptied.'
            );
        }

        // Drop indexes that reference the columns we're altering (Postgres requires this).
        DB::statement('DROP INDEX IF EXISTS subject;');
        DB::statement('DROP INDEX IF EXISTS causer;');

        // Use a real cast (text -> uuid) instead of `USING NULL`. With the
        // row-count guard above, the table is empty so the cast is a no-op on
        // data, but the safer expression survives a future code reorder where
        // the guard moves elsewhere.
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE uuid USING subject_id::text::uuid;');
        DB::statement('ALTER TABLE activity_log ALTER COLUMN causer_id  TYPE uuid USING causer_id::text::uuid;');

        // Re-create the indexes Spatie's migration originally created.
        DB::statement('CREATE INDEX subject ON activity_log (subject_type, subject_id);');
        DB::statement('CREATE INDEX causer  ON activity_log (causer_type, causer_id);');
    }

    public function down(): void
    {
        // Reverse the cast back to bigint. The data is unrecoverable on
        // rollback (uuid -> bigint has no safe cast), so we use USING NULL on
        // the way down — operators rolling back accept the loss.
        DB::statement('DROP INDEX IF EXISTS subject;');
        DB::statement('DROP INDEX IF EXISTS causer;');

        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE bigint USING NULL;');
        DB::statement('ALTER TABLE activity_log ALTER COLUMN causer_id  TYPE bigint USING NULL;');

        DB::statement('CREATE INDEX subject ON activity_log (subject_type, subject_id);');
        DB::statement('CREATE INDEX causer  ON activity_log (causer_type, causer_id);');
    }
};
