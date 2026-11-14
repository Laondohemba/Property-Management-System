<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Enums\Role;
use App\Modules\Inventory\Models\Amenity;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * Room type setup under /bff/settings/room-types (SYS-04).
 */
class RoomTypeSettingsTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->hotel = $this->property();
    }

    private function valid(array $overrides = []): array
    {
        return [
            'name' => 'Deluxe King',
            'code' => 'dlx',
            'description' => 'Corner room with a view.',
            'base_occupancy' => 2,
            'max_adults' => 2,
            'max_children' => 2,
            'max_occupancy' => 4,
            'bed_configuration' => [['type' => 'king', 'count' => 1], ['type' => 'sofa_bed', 'count' => 1]],
            ...$overrides,
        ];
    }

    private function asManager()
    {
        return $this->actingAs($this->staff($this->hotel, Role::PropertyManager))
            ->withHeader('X-Property-Id', $this->hotel->public_id);
    }

    public function test_property_managers_can_create_a_room_type_with_amenities(): void
    {
        $wifi = Amenity::factory()->for($this->hotel)->create(['name' => 'Wi-Fi']);
        $safe = Amenity::factory()->for($this->hotel)->create(['name' => 'Safe']);

        $this->asManager()
            ->postJson('/bff/settings/room-types', $this->valid(['amenity_ids' => [$wifi->public_id, $safe->public_id]]))
            ->assertCreated()
            ->assertJsonPath('data.code', 'DLX')
            ->assertJsonPath('data.bed_configuration.0', ['type' => 'king', 'label' => 'King', 'count' => 1])
            ->assertJsonPath('data.amenities.0.name', 'Safe')
            ->assertJsonPath('data.amenities.1.name', 'Wi-Fi')
            ->assertJsonPath('data.rooms_count', 0);

        $roomType = RoomType::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->hotel->id, $roomType->property_id);
        $this->assertSame($this->hotel->organization_id, $roomType->organization_id);
    }

    public function test_capacity_must_be_consistent(): void
    {
        $this->asManager()
            ->postJson('/bff/settings/room-types', $this->valid(['base_occupancy' => 5, 'max_occupancy' => 4, 'max_adults' => 2, 'max_children' => 1]))
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'base_occupancy'])
            ->assertJsonFragment(['field' => 'max_occupancy']);
    }

    public function test_bed_types_are_validated(): void
    {
        $this->asManager()
            ->postJson('/bff/settings/room-types', $this->valid(['bed_configuration' => [['type' => 'hammock', 'count' => 1]]]))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'bed_configuration.0.type');
    }

    public function test_amenities_of_another_property_are_rejected(): void
    {
        $foreign = Amenity::factory()->for($this->property())->create();

        $this->asManager()
            ->postJson('/bff/settings/room-types', $this->valid(['amenity_ids' => [$foreign->public_id]]))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'amenity_ids.0');
    }

    public function test_codes_are_unique_per_property(): void
    {
        RoomType::factory()->for($this->hotel)->create(['code' => 'DLX']);
        RoomType::factory()->for($this->property())->create(['code' => 'STD']);

        $this->asManager()->postJson('/bff/settings/room-types', $this->valid())->assertStatus(422)->assertJsonPath('errors.0.field', 'code');
        $this->asManager()->postJson('/bff/settings/room-types', $this->valid(['code' => 'STD']))->assertCreated();
    }

    public function test_room_types_can_be_updated_and_amenities_resynced(): void
    {
        $roomType = RoomType::factory()->for($this->hotel)->create(['max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3]);
        $wifi = Amenity::factory()->for($this->hotel)->create(['name' => 'Wi-Fi']);
        $roomType->amenities()->attach(Amenity::factory()->for($this->hotel)->create(['name' => 'Kettle']));

        $this->asManager()
            ->patchJson("/bff/settings/room-types/{$roomType->public_id}", ['name' => 'Renamed', 'is_active' => false, 'amenity_ids' => [$wifi->public_id]])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonCount(1, 'data.amenities')
            ->assertJsonPath('data.amenities.0.name', 'Wi-Fi');
    }

    public function test_partial_updates_are_checked_against_stored_capacity(): void
    {
        $roomType = RoomType::factory()->for($this->hotel)->create(['base_occupancy' => 2, 'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3]);

        $this->asManager()
            ->patchJson("/bff/settings/room-types/{$roomType->public_id}", ['max_occupancy' => 5])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'max_occupancy');
    }

    public function test_room_types_of_another_property_return_404(): void
    {
        $foreign = RoomType::factory()->for($this->property())->create();

        $this->asManager()->getJson("/bff/settings/room-types/{$foreign->public_id}")->assertNotFound();
        $this->asManager()->patchJson("/bff/settings/room-types/{$foreign->public_id}", ['name' => 'x'])->assertNotFound();
    }

    public function test_the_list_is_scoped_to_the_active_property_and_counts_rooms(): void
    {
        $deluxe = RoomType::factory()->for($this->hotel)->create(['name' => 'Deluxe', 'sort_order' => 2]);
        RoomType::factory()->for($this->hotel)->create(['name' => 'Standard', 'sort_order' => 1]);
        RoomType::factory()->for($this->property())->create(['name' => 'Elsewhere']);
        Room::factory()->count(3)->for($deluxe)->create();

        $this->asManager()
            ->getJson('/bff/settings/room-types')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Standard')
            ->assertJsonPath('data.1.rooms_count', 3)
            ->assertJsonPath('meta.bed_types.0.value', 'single');
    }

    public function test_roles_without_settings_rooms_are_forbidden(): void
    {
        $this->actingAs($this->staff($this->hotel, Role::FrontDeskAgent))
            ->withHeader('X-Property-Id', $this->hotel->public_id)
            ->getJson('/bff/settings/room-types')
            ->assertForbidden();
    }

    public function test_the_property_header_is_required(): void
    {
        $this->actingAs($this->staff($this->hotel, Role::PropertyManager))
            ->getJson('/bff/settings/room-types')
            ->assertStatus(400)
            ->assertJsonPath('code', 'property_required');
    }
}
