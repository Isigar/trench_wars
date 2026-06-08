<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Renders a one-line descriptive subheading under the page title on every
 * resource page (List / View / Edit / Create).
 *
 * The copy is resolved from lang/en/admin.php under
 * `admin.subheadings.<resource_slug>`, where the slug is the snake_case of the
 * resource class basename minus the "Resource" suffix
 * (UserResource -> user, DiscordGuildResource -> discord_guild). Resources opt
 * in simply by having their pages extend the App\Filament\Base\* page classes
 * (which compose this trait) — no per-resource code is required.
 *
 * A missing key falls back to the Filament default (parent::getSubheading()),
 * so a raw translation key is never shown to the admin.
 */
trait HasResourceSubheading
{
    public function getSubheading(): string|Htmlable|null
    {
        /** @var class-string $resource */
        $resource = static::getResource();

        $slug = Str::of(class_basename($resource))
            ->beforeLast('Resource')
            ->snake()
            ->toString();

        $key = "admin.subheadings.{$slug}";

        if (! Lang::has($key)) {
            return parent::getSubheading();
        }

        $value = Lang::get($key);

        return is_string($value) ? $value : parent::getSubheading();
    }
}
