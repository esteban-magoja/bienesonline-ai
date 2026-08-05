<?php

namespace Database\Factories;

use App\Models\UserProfileSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfileSetting>
 */
class UserProfileSettingFactory extends Factory
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
            'headline' => $this->faker->sentence(6),
            'website_url' => $this->faker->url(),
            'social_links' => ['instagram' => 'https://instagram.com/example'],
            'office_hours' => ['monday' => '09:00-18:00'],
            'show_email' => true,
            'show_phone' => true,
            'show_address' => true,
        ];
    }
}
