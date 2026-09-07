<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;
use Wave\Plan;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'features' => fake()->sentence(),
            'active' => true,
            'role_id' => Role::query()->where('name', 'premium')->value('id')
                ?? Role::query()->value('id'),
            'default' => false,
            'monthly_price' => '10',
            'yearly_price' => '99',
        ];
    }
}
