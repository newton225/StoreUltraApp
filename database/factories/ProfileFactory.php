<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => null,
            'label_fr' => fake()->unique()->jobTitle(),
            'label_en' => fake()->unique()->jobTitle(),
            'restricted_to_zones' => false,
            'is_system' => false,
            'permissions' => [],
        ];
    }

    /**
     * A profile that grants the given permissions.
     *
     * @param  list<Permission>  $permissions
     */
    public function granting(array $permissions): static
    {
        return $this->state(fn (): array => ['permissions' => array_map(fn (Permission $permission): string => $permission->value, $permissions)]);
    }

    /**
     * A profile whose users only see the sub-prefectures they are assigned to.
     */
    public function restrictedToZones(): static
    {
        return $this->state(fn (): array => ['restricted_to_zones' => true]);
    }
}
