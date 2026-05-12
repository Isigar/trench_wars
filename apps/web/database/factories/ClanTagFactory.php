<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClanTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClanTag>
 */
class ClanTagFactory extends Factory
{
    protected $model = ClanTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug(fake()->unique()->word());

        return [
            'slug' => $slug,
            'label' => ['en' => Str::title($slug)],
            'color' => fake()->hexColor(),
        ];
    }
}
