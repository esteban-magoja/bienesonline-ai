<?php

namespace Database\Factories;

use App\Models\UserProfileService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfileService>
 */
class UserProfileServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name_i18n' => ['es' => $this->faker->sentence(3), 'en' => $this->faker->sentence(3)],
            'description_i18n' => ['es' => $this->faker->paragraph(), 'en' => $this->faker->paragraph()],
            'icon' => 'building-office',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
