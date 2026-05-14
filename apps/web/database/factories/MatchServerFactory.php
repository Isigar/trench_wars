<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MatchServer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Source: .planning/phases/08-rcon-automation/08-03-PLAN.md task 1.
 *
 * Replaces the Wave 0 stub (plan 08-01). `credentials_encrypted` is passed as a
 * plain array — the model's `encrypted:array` cast handles encryption at write
 * time. The per-line PHPStan ignore annotations on the stub are removed now that
 * `App\Models\MatchServer` exists and the generic
 * `@extends Factory<MatchServer>` resolves.
 *
 * @extends Factory<MatchServer>
 */
class MatchServerFactory extends Factory
{
    protected $model = MatchServer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Server ' . fake()->unique()->numerify('##'),
            'host' => 'crcon-' . fake()->unique()->word() . '.example.com',
            'port_rcon' => 8010 + fake()->unique()->numberBetween(0, 200),
            'region' => fake()->randomElement(['eu-central', 'us-east', 'us-west', 'ap-southeast']),
            'credentials_encrypted' => ['api_token' => 'fake-bearer-' . Str::random(40)],
            'is_active' => true,
            'last_test_at' => null,
            'last_test_status' => null,
            'last_test_error' => null,
        ];
    }

    /**
     * State: server is soft-disabled (is_active=false). Filament admin can still
     * see it; the scheduler ignores it.
     */
    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
