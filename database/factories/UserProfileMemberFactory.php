<?php

namespace Database\Factories;

use App\Models\UserProfileMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfileMember>
 */
class UserProfileMemberFactory extends Factory
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
            'name' => $this->faker->name(),
            'role' => 'Asesor inmobiliario',
            'bio_i18n' => ['es' => $this->faker->paragraph(), 'en' => $this->faker->paragraph()],
            'specialties' => ['Ventas', 'Alquileres'],
            'areas' => ['Centro'],
            'show_phone' => false,
            'show_email' => false,
            'sort_order' => 0,
            'is_visible' => true,
        ];
    }
}
