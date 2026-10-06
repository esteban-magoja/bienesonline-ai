<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SearchQuery>
 */
class SearchQueryFactory extends Factory
{
    public function definition(): array
    {
        $query = fake()->unique()->words(4, true);

        return [
            'country' => 'Argentina',
            'query' => $query,
            'slug' => Str::slug($query),
            'results_count' => fake()->numberBetween(0, 50),
            'active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => ['active' => true]);
    }
}
