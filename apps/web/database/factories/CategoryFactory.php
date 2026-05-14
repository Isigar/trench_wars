<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Source: .planning/phases/07-cms/07-03-PLAN.md task 1(d).
 *
 * Replaces the Wave 0 stub (07-01). The per-line phpstan-ignore annotations
 * from the stub are removed; canonical generic `@extends Factory<Category>` is
 * restored now that App\Models\Category exists.
 *
 * Default scope: a unique slug (word + 4-char nonce) and an EN-only name.
 *
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'slug' => Str::slug($word) . '-' . Str::lower(Str::random(4)),
            'name' => ['en' => Str::title($word)],
        ];
    }
}
