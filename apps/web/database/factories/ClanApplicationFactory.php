<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Wave 0 stub — plan 02-03 (Wave 1, Models) replaces this with the real definition.
 * Source analog: apps/web/database/factories/PlayerPrivacyFactory.php (FK pattern).
 *
 * String form of $model so PHP does not eager-load App\Models\ClanApplication before Wave 1 ships.
 * Wave 1 replaces $model with ClanApplication::class once the model exists.
 *
 * @extends Factory<Model>
 */
final class ClanApplicationFactory extends Factory
{
    /** @phpstan-ignore-next-line */
    protected $model = 'App\\Models\\ClanApplication';

    /** @return array<string, mixed> */
    public function definition(): array
    {
        throw new \RuntimeException('Wave 0 stub - plan 02-03 (Models) replaces this with real definitions.');
    }
}
