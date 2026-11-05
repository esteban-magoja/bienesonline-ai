<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailContact>
 */
class EmailContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'agency' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'country' => 'Argentina',
            'whatsapp' => '+54911' . fake()->numerify('########'),
        ];
    }
}
