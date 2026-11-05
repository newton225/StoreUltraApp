<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commercial_number' => fake()->unique()->numerify('RC-########'),
            'name' => fake()->name(),
            'phone' => fake()->numerify('0#########'),
            'zone_id' => Zone::factory(),
            'locality' => fake()->city(),
            'delivery_address' => fake()->streetAddress(),
            'contact_person' => null,
            'status' => ClientStatus::Active,
            'notes' => null,
            'created_by' => null,
        ];
    }

    /**
     * Give the client the reference the application assigns on registration.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Client $client): void {
            $client->forceFill(['reference' => sprintf('CLI-%04d', $client->id)])->save();
        });
    }
}
