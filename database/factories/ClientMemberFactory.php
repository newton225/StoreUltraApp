<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientMember>
 */
class ClientMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->name(),
            'role' => ClientMember::ROLE_ASSISTANT,
            'trade' => fake()->randomElement(['Plombier', 'Macon', 'Menuisier']),
            'phone' => fake()->numerify('0# ## ## ## ##'),
            'position' => 0,
        ];
    }
}
