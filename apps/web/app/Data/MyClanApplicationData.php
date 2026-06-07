<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ClanApplication;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Applicant-facing projection of the applicant's OWN pending ClanApplication,
 * consumed by the "Your applications" section on /my-clan. Carries the target
 * clan's display fields so the applicant can withdraw without a second lookup.
 *
 * Distinct from ClanApplicationData (the Leader/Officer incoming-review view).
 */
#[TypeScript]
final class MyClanApplicationData extends Data
{
    public function __construct(
        public string $id,
        public string $clan_name,
        public string $clan_tag,
        public string $clan_slug,
        public ?string $message,
    ) {}

    public static function fromModel(ClanApplication $application): self
    {
        // clan_id is a NOT NULL FK and is eager-loaded by the caller; the
        // null-safe access only guards the (impossible) unloaded/orphan case.
        $clan = $application->clan;

        return new self(
            id: (string) $application->id,
            clan_name: (string) $clan?->name,
            clan_tag: (string) $clan?->tag,
            clan_slug: (string) $clan?->slug,
            message: $application->message,
        );
    }
}
