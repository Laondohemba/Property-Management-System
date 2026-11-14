<?php

namespace Tests\Feature\Audit;

use App\Modules\Identity\Enums\Role;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * Created by / updated by on list and edit screens (spec §7.1) and per-record history from the audit trail.
 */
class RecordStampsTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->hotel = $this->property();
    }

    public function test_records_show_who_created_and_last_updated_them(): void
    {
        $creator = $this->staff($this->hotel, Role::PropertyManager);
        $editor = $this->staff($this->hotel, Role::PropertyManager);
        $type = RoomType::factory()->for($this->hotel)->create();

        $id = $this->actingAs($creator)->withHeader('X-Property-Id', $this->hotel->public_id)
            ->postJson('/bff/settings/rooms', ['room_type_id' => $type->public_id, 'number' => '301'])
            ->assertCreated()
            ->assertJsonPath('data.stamps.created_by.name', $creator->name)
            ->json('data.id');

        $this->actingAs($editor)->withHeader('X-Property-Id', $this->hotel->public_id)
            ->patchJson("/bff/settings/rooms/{$id}", ['floor' => '3'])
            ->assertOk()
            ->assertJsonPath('data.stamps.created_by.id', $creator->public_id)
            ->assertJsonPath('data.stamps.updated_by.id', $editor->public_id);

        $this->actingAs($editor)->withHeader('X-Property-Id', $this->hotel->public_id)
            ->getJson('/bff/settings/rooms')
            ->assertJsonPath('data.0.stamps.updated_by.name', $editor->name);
    }

    public function test_seeded_records_have_no_stamp_users(): void
    {
        RoomType::factory()->for($this->hotel)->create();

        $this->actingAs($this->superAdmin())->withHeader('X-Property-Id', $this->hotel->public_id)
            ->getJson('/bff/settings/room-types')
            ->assertJsonPath('data.0.stamps.created_by', null)
            ->assertJsonPath('data.0.stamps.updated_by', null);
    }

    public function test_the_audit_log_can_show_the_history_of_one_record(): void
    {
        $type = RoomType::factory()->for($this->hotel)->create();
        $room = Room::factory()->for($type)->create(['number' => '401', 'floor' => '4']);
        Room::factory()->for($type)->create(['number' => '402']);
        $room->update(['floor' => '5']);

        $history = $this->actingAs($this->superAdmin())
            ->getJson("/bff/audit-logs?filter[subject_type]=room&filter[subject]={$room->public_id}")
            ->assertOk()
            ->json('data');

        $this->assertSame(['Room 401 updated', 'Room 401 created'], array_column($history, 'description'));
        $this->assertSame(['floor' => '4'], $history[0]['old']);
    }

    public function test_unknown_subjects_return_no_history(): void
    {
        $this->actingAs($this->superAdmin())
            ->getJson('/bff/audit-logs?filter[subject_type]=room&filter[subject]=nope')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
