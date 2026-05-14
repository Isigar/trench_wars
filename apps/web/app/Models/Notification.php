<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 1.
 *
 * Extends `Illuminate\Notifications\DatabaseNotification` (the concrete morph model
 * Laravel ships for the `notifications` table — NOT the abstract `Notification`
 * class consumers extend in `app/Notifications/`). This subclass exists so we can
 * carry app-level conventions (UUID PK + cast declarations + table override) in
 * one place; the polymorphic morph defaults inherited from the parent (notifiable
 * morph mapping, factory creation via ->notify()) remain unchanged.
 *
 * Schema reference (plan 09-02 migration 2026_05_18_100000):
 *   id              uuid pk (default gen_random_uuid())
 *   notifiable_type text + notifiable_id uuid (uuidMorphs)
 *   type            text — Notification class FQN or databaseType() discriminator
 *   data            jsonb — Notification::toArray() payload
 *   read_at         timestamptz nullable
 *   created_at/updated_at timestamptz
 *
 * NO HasFactory: notifications rows are created via the framework's `->notify()`
 * pipeline (DatabaseChannel inserts the row). A factory would invite tests to
 * bypass the dispatch path and assert against synthetic rows that never match
 * the production payload shape.
 */
class Notification extends DatabaseNotification
{
    /**
     * Explicit table override — Laravel's DatabaseNotification defaults to
     * `notifications` already, but pinning the table here makes the binding
     * grep-able and immune to a future framework rename.
     */
    protected $table = 'notifications';

    /**
     * UUID PK contract — mirrors the Phase 9 migration (id uuid DEFAULT
     * gen_random_uuid()). DatabaseNotification's parent already sets these,
     * but pinning them locally keeps PHPStan + tests deterministic.
     */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'string';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }
}
