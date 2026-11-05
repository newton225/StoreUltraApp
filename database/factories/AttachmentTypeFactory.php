<?php

namespace Database\Factories;

use App\Models\AttachmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttachmentType>
 */
class AttachmentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('????-####'),
            'label_fr' => fake()->words(2, true),
            'label_en' => fake()->words(2, true),
            'applies_to' => 'company',
            'required' => false,
            'has_expiry' => false,
            'on_documents' => false,
            'active' => true,
        ];
    }

    public function forKind(string $kind): static
    {
        return $this->state(['applies_to' => $kind]);
    }

    public function required(): static
    {
        return $this->state(['required' => true]);
    }
}
