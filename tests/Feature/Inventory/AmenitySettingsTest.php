<?php

namespace Tests\Feature\Inventory;

use App\Modules\Inventory\Models\Amenity;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

class AmenitySettingsTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->hotel = $this->property();
    }

    private function asAdmin()
    {
        return $this->actingAs($this->superAdmin())->withHeader('X-Property-Id', $this->hotel->public_id);
    }

    public function test_amenities_can_be_created_renamed_and_deleted(): void
    {
        $id = $this->asAdmin()->postJson('/bff/settings/amenities', ['name' => ' Wi-Fi '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Wi-Fi')
            ->json('data.id');

        $this->asAdmin()->patchJson("/bff/settings/amenities/{$id}", ['name' => 'Free Wi-Fi'])->assertOk()->assertJsonPath('data.name', 'Free Wi-Fi');
        $this->asAdmin()->deleteJson("/bff/settings/amenities/{$id}")->assertNoContent();

        $this->assertSame(0, Amenity::forProperty($this->hotel)->count());
    }

    public function test_names_are_unique_per_property(): void
    {
        Amenity::factory()->for($this->hotel)->create(['name' => 'Safe']);

        $this->asAdmin()->postJson('/bff/settings/amenities', ['name' => 'Safe'])->assertStatus(422);
    }

    public function test_deleting_an_amenity_detaches_it_from_room_types(): void
    {
        $amenity = Amenity::factory()->for($this->hotel)->create(['name' => 'Balcony']);
        $roomType = RoomType::factory()->for($this->hotel)->create();
        $roomType->amenities()->attach($amenity);

        $this->asAdmin()->getJson('/bff/settings/amenities')->assertJsonPath('data.0.room_types_count', 1);
        $this->asAdmin()->deleteJson("/bff/settings/amenities/{$amenity->public_id}")->assertNoContent();

        $this->assertCount(0, $roomType->refresh()->amenities);
    }

    public function test_amenities_of_another_property_return_404(): void
    {
        $foreign = Amenity::factory()->for($this->property())->create();

        $this->asAdmin()->deleteJson("/bff/settings/amenities/{$foreign->public_id}")->assertNotFound();
    }

    public function test_the_dashboard_counts_active_rooms_and_room_types(): void
    {
        $roomType = RoomType::factory()->for($this->hotel)->create();
        Room::factory()->count(2)->for($roomType)->create();
        Room::factory()->for($roomType)->create(['is_active' => false]);

        $this->asAdmin()
            ->getJson('/bff/dashboard')
            ->assertOk()
            ->assertJsonPath('data.tiles.0', ['key' => 'rooms', 'label' => 'Active rooms', 'value' => 2])
            ->assertJsonPath('data.tiles.1', ['key' => 'room_types', 'label' => 'Room types', 'value' => 1]);
    }
}
