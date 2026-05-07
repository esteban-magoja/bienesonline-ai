<?php

namespace Database\Factories;

use App\Models\PropertyRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyRequest>
 */
class PropertyRequestFactory extends Factory
{
    protected $model = PropertyRequest::class;

    public function definition(): array
    {
        return [
            'user_id'            => User::factory(),
            'title'              => $this->faker->sentence(4),
            'description'        => $this->faker->paragraph(),
            'property_type'      => $this->faker->randomElement(['house', 'apartment', 'land', 'commercial', 'office']),
            'transaction_type'   => $this->faker->randomElement(['sale', 'rent']),
            'min_budget'         => $this->faker->optional()->randomFloat(2, 50000, 200000),
            'max_budget'         => $this->faker->optional()->randomFloat(2, 200000, 1000000),
            'currency'           => 'USD',
            'min_bedrooms'       => null,
            'min_bathrooms'      => null,
            'min_parking_spaces' => null,
            'min_area'           => null,
            'city'               => $this->faker->city(),
            'state'              => $this->faker->state(),
            'country'            => 'Argentina',
            'is_active'          => true,
            'expires_at'         => null,
        ];
    }
}

