<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ClanApplication;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § clan_applications.
 *
 * Status transitions: pending → accepted | declined | cancelled
 * (Pattern 6 in 02-RESEARCH.md).
 *
 * `applicant_username` is a convenience denormalisation from the related User —
 * populated by fromModel() for the Vue Applications tab component.
 *
 * Use `ClanApplicationData::fromModel($app)` to construct from an Eloquent
 * model — auto-mapping cannot resolve the denormalised User field.
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
        public ?string $applicant_username,
    ) {}

    /**
     * Build a ClanApplicationData from an Eloquent ClanApplication model.
     *
     * Requires `applicant` to be eager-loaded or already on the model.
     */
    public static function fromModel(ClanApplication $app): self
    {
        $applicant = $app->relationLoaded('applicant') ? $app->applicant : null;

        return new self(
            id: $app->id,
            clan_id: $app->clan_id,
            applicant_user_id: $app->applicant_user_id,
            status: $app->status,
            message: $app->message,
            decided_at: $app->decided_at !== null ? (string) $app->decided_at : null,
            decided_by: $app->decided_by,
            applicant_username: $applicant?->username,
        );
    }

    /**
     * Collect a list of ClanApplicationData from an Eloquent collection.
     *
     * @param  iterable<ClanApplication>  $apps
     * @return list<self>
     */
    public static function collectFromModels(iterable $apps): array
    {
        $result = [];
        foreach ($apps as $app) {
            $result[] = self::fromModel($app);
        }

        return $result;
    }
}
