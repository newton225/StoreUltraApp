<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'proforma',
            'number' => fake()->unique()->numerify('PRO-2026-#####'),
            'subject_type' => null,
            'subject_id' => null,
            'locale' => 'fr',
            'file_path' => 'documents/proforma/test.pdf',
            'issued_at' => now(),
        ];
    }
}
