<?php

namespace Database\Factories;

use App\Models\TourCompletion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourCompletion>
 */
class TourCompletionFactory extends Factory
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
            'tour' => 'dashboard',
            'completed_at' => now(),
        ];
    }
}
