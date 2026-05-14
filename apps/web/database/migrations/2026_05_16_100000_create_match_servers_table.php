<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/08-rcon-automation/08-02-PLAN.md task 1 +
 *         08-RESEARCH.md § Standard Stack (Postgres encrypted credentials column).
 *
 * League-owned RCON server registry (D-005, REQ-constraint-league-owns-servers).
 * `credentials_encrypted` is jsonb at the schema layer but the application layer
 * (plan 08-03 model) writes ONLY via Laravel's `encrypted:array` cast — values at
 * rest are Laravel envelope ciphertext under APP_KEY (T-08-02-04 mitigation).
 *
 * Column inventory:
 *   - id                    uuid pk (default gen_random_uuid())
 *   - name                  text NOT NULL — admin-friendly server label (e.g. "Berlin #1")
 *   - host                  text NOT NULL — DNS/IP of CRCON HTTP API + WebSocket endpoint
 *   - port_rcon             integer NOT NULL — game-server RCON port (CRCON proxies via this)
 *   - region                text NULL — e.g. "eu-central", "us-east" (free-form; not enum)
 *   - credentials_encrypted jsonb NOT NULL — encrypted:array cast at plan 08-03 model
 *   - is_active             boolean default true — soft-disable without deleting
 *   - last_test_at          timestamptz NULL — Filament "Test Connection" timestamp
 *   - last_test_status      text NULL — 'ok' | 'error' (CHECK constraint)
 *   - last_test_error       text NULL — error message from last failed probe (PII-free)
 *   - timestamps()          — created_at + updated_at ALTERed to timestamptz post-create
 *
 * CHECK constraint:
 *   match_servers_last_test_status_check — NULL or ('ok','error')
 *
 * No FKs: this is a root table (admin-owned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_servers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('name');
            $table->text('host');
            $table->integer('port_rcon');
            $table->text('region')->nullable();
            $table->jsonb('credentials_encrypted');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_test_at')->nullable();
            $table->text('last_test_status')->nullable();
            $table->text('last_test_error')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE match_servers ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE match_servers ADD CONSTRAINT match_servers_last_test_status_check CHECK (last_test_status IS NULL OR last_test_status IN ('ok','error'));");
        DB::statement("ALTER TABLE match_servers ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE match_servers ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('match_servers');
    }
};
