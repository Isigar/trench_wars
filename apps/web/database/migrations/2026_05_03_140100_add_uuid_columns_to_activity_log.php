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
        // Drop indexes that reference the columns we're altering (Postgres requires this).
        DB::statement('DROP INDEX IF EXISTS subject;');
        DB::statement('DROP INDEX IF EXISTS causer;');

        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE uuid USING NULL;');
        DB::statement('ALTER TABLE activity_log ALTER COLUMN causer_id  TYPE uuid USING NULL;');

        // Re-create the indexes Spatie's migration originally created.
        DB::statement('CREATE INDEX subject ON activity_log (subject_type, subject_id);');
        DB::statement('CREATE INDEX causer  ON activity_log (causer_type, causer_id);');
    }

    public function down(): void
    {
        // Down-migration not provided — would lose UUID data; manual rollback required if needed.
    }
};
