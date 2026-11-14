<?php

namespace Database\Factories;

use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'organization_id' => fn (array $attributes) => Property::withoutGlobalScopes()->find($attributes['property_id'])?->organization_id,
            'name' => fake()->randomElement(['Standard', 'Deluxe', 'Executive', 'Family']).' Room',
            'code' => Str::upper(Str::random(4)),
            'base_occupancy' => 2,
            'max_adults' => 2,
            'max_children' => 1,
            'max_occupancy' => 3,
            'bed_configuration' => [['type' => 'queen', 'count' => 1]],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
