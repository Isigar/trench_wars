<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Source: .planning/phases/07-cms/07-03-PLAN.md task 1(c).
 *
 * Replaces the Wave 0 stub (07-01) — the per-line phpstan-ignore annotations
 * from the stub are removed; canonical generic `@extends Factory<Article>` is
 * restored now that App\Models\Article exists.
 *
 * Default scope: a fresh Category per row (via Category::factory()). Author
 * is null by default to match the migration's nullable author_user_id; pass
 * ->for($user, 'author') to attach. Status='draft', allow_discord_announce=true
 * (Open Question 1 LOCKED inline — per-article opt-in flag defaults on).
 *
 * Body is a minimal Tiptap-shaped JSON doc (`{type: doc, content: [...]}`)
 * — the public renderer (plan 07-05) consumes this shape via tiptap_converter.
 *
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'slug' => Str::slug($title) . '-' . Str::random(6),
            'category_id' => Category::factory(),
            'title' => ['en' => $title],
            'excerpt' => ['en' => fake()->sentence(12)],
            'body' => ['en' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => fake()->paragraph()],
                        ],
                    ],
                ],
            ]],
            'status' => 'draft',
            'scheduled_at' => null,
            'published_at' => null,
            'author_user_id' => null,
            'allow_discord_announce' => true,
        ];
    }
}
