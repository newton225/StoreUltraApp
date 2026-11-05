<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\AttachmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachment_type_id' => AttachmentType::factory(),
            'owner_id' => null,
            'number' => fake()->unique()->bothify('CI-ABJ-####-?-##'),
            'issued_on' => null,
            'expires_on' => null,
            'file_path' => null,
            'notes' => null,
        ];
    }
}
