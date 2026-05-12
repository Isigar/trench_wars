<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § clan_applications.
 *
 * Status transitions: pending → accepted | declined | cancelled
 * (Pattern 6 in 02-RESEARCH.md).
 */
#[TypeScript]
final class ClanApplicationData extends Data
{
    public function __construct(
        public string $id,
        public string $clan_id,
        public string $applicant_user_id,
        public string $status,
        public ?string $message,
        public ?string $decided_at,
        public ?string $decided_by,
    ) {}
}
