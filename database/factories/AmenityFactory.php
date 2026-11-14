<?php

namespace Database\Factories;

use App\Modules\Inventory\Models\Amenity;
use App\Modules\Property\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Amenity>
 */
class AmenityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'organization_id' => fn (array $attributes) => Property::withoutGlobalScopes()->whereKey($attributes['property_id'])->value('organization_id'),
            'name' => fake()->unique()->randomElement(['Wi-Fi', 'Air conditioning', 'Mini bar', 'Safe', 'Balcony', 'Sea view', 'Smart TV', 'Kettle', 'Bathtub', 'Workspace']),
        ];
    }
}
