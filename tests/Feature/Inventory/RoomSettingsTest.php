<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Enums\Role;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * Room setup under /bff/settings/rooms (SYS-04).
 */
class RoomSettingsTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotel;

    private RoomType $standard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->hotel = $this->property();
        $this->standard = RoomType::factory()->for($this->hotel)->create(['name' => 'Standard', 'code' => 'STD']);
    }

    private function asAdmin()
    {
        return $this->actingAs($this->superAdmin())->withHeader('X-Property-Id', $this->hotel->public_id);
    }

    public function test_a_room_can_be_created_with_default_statuses(): void
    {
        $this->asAdmin()
            ->postJson('/bff/settings/rooms', ['room_type_id' => $this->standard->public_id, 'number' => '101a', 'floor' => '1'])
            ->assertCreated()
            ->assertJsonPath('data.number', '101A')
            ->assertJsonPath('data.room_type.code', 'STD')
            ->assertJsonPath('data.housekeeping_status', ['value' => 'clean', 'label' => 'Clean'])
            ->assertJsonPath('data.occupancy_status', ['value' => 'vacant', 'label' => 'Vacant']);
    }

    public function test_room_numbers_are_unique_per_property(): void
    {
        Room::factory()->for($this->standard)->create(['number' => '101']);
        Room::factory()->for(RoomType::factory()->for($this->property()))->create(['number' => '102']);

        $this->asAdmin()->postJson('/bff/settings/rooms', ['room_type_id' => $this->standard->public_id, 'number' => '101'])
            ->assertStatus(422)->assertJsonPath('errors.0.message', 'This room number is already in use at this property.');

        $this->asAdmin()->postJson('/bff/settings/rooms', ['room_type_id' => $this->standard->public_id, 'number' => '102'])->assertCreated();
    }

    public function test_room_types_of_another_property_cannot_be_used(): void
    {
        $foreign = RoomType::factory()->for($this->property())->create();

        $this->asAdmin()
            ->postJson('/bff/settings/rooms', ['room_type_id' => $foreign->public_id, 'number' => '101'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'room_type_id');
    }

    public function test_a_range_of_rooms_can_be_created_at_once(): void
    {
        $this->asAdmin()
            ->postJson('/bff/settings/rooms/range', ['room_type_id' => $this->standard->public_id, 'floor' => '2', 'from' => 201, 'to' => 205])
            ->assertCreated()
            ->assertJsonPath('meta.created', 5)
            ->assertJsonPath('data.0.number', '201')
            ->assertJsonPath('data.4.number', '205');

        $this->assertSame(5, Room::forProperty($this->hotel)->where('floor', '2')->count());
    }

    public function test_a_range_with_existing_numbers_creates_nothing(): void
    {
        Room::factory()->for($this->standard)->create(['number' => 'B203']);

        $this->asAdmin()
            ->postJson('/bff/settings/rooms/range', ['room_type_id' => $this->standard->public_id, 'prefix' => 'b', 'from' => 201, 'to' => 205])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.message', 'These room numbers already exist: B203.');

        $this->assertSame(1, Room::forProperty($this->hotel)->count());
    }

    public function test_ranges_are_capped(): void
    {
        $this->asAdmin()
            ->postJson('/bff/settings/rooms/range', ['room_type_id' => $this->standard->public_id, 'from' => 1, 'to' => 500])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'to');
    }

    public function test_rooms_can_be_moved_to_another_type_and_deactivated(): void
    {
        $room = Room::factory()->for($this->standard)->create(['number' => '101']);
        $deluxe = RoomType::factory()->for($this->hotel)->create(['code' => 'DLX']);

        $this->asAdmin()
            ->patchJson("/bff/settings/rooms/{$room->public_id}", ['room_type_id' => $deluxe->public_id, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.room_type.code', 'DLX')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_the_list_filters_and_sorts_room_numbers_naturally(): void
    {
        $deluxe = RoomType::factory()->for($this->hotel)->create(['code' => 'DLX']);
        foreach (['110', '9', '101'] as $number) {
            Room::factory()->for($this->standard)->create(['number' => $number, 'floor' => '1']);
        }
        Room::factory()->for($deluxe)->create(['number' => '201', 'floor' => '2']);
        Room::factory()->for(RoomType::factory()->for($this->property()))->create(['number' => '999']);

        $this->asAdmin()
            ->getJson('/bff/settings/rooms')
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.floors', ['1', '2'])
            ->assertJsonPath('data.0.number', '9')
            ->assertJsonPath('data.1.number', '101')
            ->assertJsonPath('data.2.number', '110');

        $this->asAdmin()
            ->getJson("/bff/settings/rooms?filter[room_type]={$deluxe->public_id}")
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.number', '201');

        $this->asAdmin()->getJson('/bff/settings/rooms?filter[search]=10')->assertJsonPath('meta.total', 2);
    }

    public function test_rooms_of_another_property_return_404(): void
    {
        $foreign = Room::factory()->for(RoomType::factory()->for($this->property()))->create();

        $this->asAdmin()->getJson("/bff/settings/rooms/{$foreign->public_id}")->assertNotFound();
    }

    public function test_housekeeping_cannot_manage_rooms(): void
    {
        $this->actingAs($this->staff($this->hotel, Role::HousekeepingSupervisor))
            ->withHeader('X-Property-Id', $this->hotel->public_id)
            ->postJson('/bff/settings/rooms', ['room_type_id' => $this->standard->public_id, 'number' => '101'])
            ->assertForbidden();
    }
}
