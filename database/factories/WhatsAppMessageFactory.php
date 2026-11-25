<?php

namespace Database\Factories;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppMessage>
 */
class WhatsAppMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'whatsapp_conversation_id' => WhatsAppConversation::factory(),
            'wamid' => 'wamid.'.fake()->unique()->bothify('????????????????'),
            'direction' => WhatsAppMessage::DIRECTION_IN,
            'type' => 'text',
            'body' => fake()->sentence(),
            'status' => 'received',
            'sent_at' => now(),
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn (): array => [
            'direction' => WhatsAppMessage::DIRECTION_OUT,
            'status' => 'sent',
        ]);
    }
}
