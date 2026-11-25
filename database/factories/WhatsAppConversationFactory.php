<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppConversation>
 */
class WhatsAppConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'wa_id' => '549'.fake()->unique()->numerify('##########'),
            'profile_name' => fake()->name(),
            'last_message_at' => now(),
            'last_inbound_at' => now(),
            'last_message_preview' => fake()->sentence(),
            'unread_count' => 0,
        ];
    }

    /**
     * El usuario escribió hace más de 24 h: solo se puede responder con un template.
     */
    public function windowClosed(): static
    {
        return $this->state(fn (): array => [
            'last_inbound_at' => now()->subHours(30),
            'last_message_at' => now()->subHours(30),
        ]);
    }
}
