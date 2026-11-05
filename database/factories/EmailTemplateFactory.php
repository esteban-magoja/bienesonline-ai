<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'subject' => 'Hola {nombre}, novedades de bienesonline.ai',
            'body' => '<p>Hola {nombre},</p><p>Te escribimos de bienesonline.ai.</p>',
        ];
    }
}
