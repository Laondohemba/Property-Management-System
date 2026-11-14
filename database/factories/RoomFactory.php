<?php

namespace Database\Factories;

use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'property_id' => fn (array $attributes) => RoomType::withoutGlobalScopes()->whereKey($attributes['room_type_id'])->value('property_id'),
            'organization_id' => fn (array $attributes) => RoomType::withoutGlobalScopes()->whereKey($attributes['room_type_id'])->value('organization_id'),
            'number' => (string) fake()->unique()->numberBetween(100, 9999),
            'floor' => (string) fake()->numberBetween(1, 9),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
