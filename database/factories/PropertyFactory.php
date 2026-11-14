<?php

namespace Database\Factories;

use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company().' Hotel',
            'code' => Str::upper(Str::random(6)),
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
            'business_date' => now('Africa/Lagos')->toDateString(),
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'city' => fake()->city(),
            'country' => 'NG',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
