<?php

namespace Tests\Feature\Inventory;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryBlock;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Inventory\Models\RoomTypeInventory;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * /bff/inventory (CHN-01) and /bff/inventory/blocks (CAL-06).
 */
class InventoryApiTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotel;

    private RoomType $deluxe;

    private RoomType $standard;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->hotel = $this->property(['business_date' => '2026-12-01']);
        $this->standard = RoomType::factory()->for($this->hotel)->create(['code' => 'STD', 'name' => 'Standard', 'sort_order' => 1]);
        $this->deluxe = RoomType::factory()->for($this->hotel)->create(['code' => 'DLX', 'name' => 'Deluxe', 'sort_order' => 2]);
        Room::factory()->count(4)->for($this->standard)->create();
        Room::factory()->count(2)->for($this->deluxe)->create();
        $this->manager = $this->staff($this->hotel, Role::RevenueManager);
    }

    public function test_the_grid_shows_room_types_by_night_with_a_totals_row(): void
    {
        $this->asRevenueManager()->getJson('/bff/inventory?from=2026-12-01&to=2026-12-07')
            ->assertOk()
            ->assertJsonPath('data.first_date', '2026-12-01')
            ->assertJsonPath('data.last_date', '2027-01-29')
            ->assertJsonCount(7, 'data.dates')
            ->assertJsonPath('data.rows.0.room_type.code', 'STD')
            ->assertJsonPath('data.rows.0.days.0.total', 4)
            ->assertJsonPath('data.rows.0.days.0.available', 4)
            ->assertJsonPath('data.rows.1.room_type.code', 'DLX')
            ->assertJsonPath('data.totals.6.total', 6)
            ->assertJsonPath('meta.block_types.0.value', 'out_of_order');
    }

    public function test_nights_beyond_the_ledger_are_shown_from_the_current_room_count(): void
    {
        $this->asRevenueManager()->getJson('/bff/inventory?from=2027-01-29&to=2027-02-02')
            ->assertOk()
            ->assertJsonPath('data.rows.0.days.0.total', 4)
            ->assertJsonPath('data.rows.0.days.4.total', 4);

        // Reading never writes.
        $this->assertFalse(RoomTypeInventory::query()->where('date', '2027-02-02')->exists());
    }

    public function test_the_grid_range_is_limited(): void
    {
        $this->asRevenueManager()->getJson('/bff/inventory?from=2026-12-01&to=2027-06-01')
            ->assertUnprocessable()->assertJsonPath('errors.0.field', 'to');

        $this->asRevenueManager()->getJson('/bff/inventory?from=2026-12-05&to=2026-12-01')
            ->assertUnprocessable();
    }

    public function test_a_block_reduces_availability_and_is_audited(): void
    {
        $this->createBlock(['quantity' => 2, 'from_date' => '2026-12-03', 'to_date' => '2026-12-04', 'type' => 'out_of_order', 'reason' => 'Bathroom refit'])
            ->assertCreated()
            ->assertJsonPath('data.room_type.code', 'DLX')
            ->assertJsonPath('data.type_label', 'Out of order')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.stamps.created_by.name', fn ($name) => is_string($name));

        $this->asRevenueManager()->getJson('/bff/inventory?from=2026-12-02&to=2026-12-05')
            ->assertJsonPath('data.rows.1.days.0.available', 2)
            ->assertJsonPath('data.rows.1.days.1.available', 0)
            ->assertJsonPath('data.rows.1.days.1.blocked', 2)
            ->assertJsonPath('data.rows.1.days.3.available', 2);

        $this->assertTrue(AuditEntry::query()->where('subject_type', (new InventoryBlock)->getMorphClass())->where('event', 'created')->exists());
    }

    public function test_a_block_is_refused_when_a_night_has_too_few_units(): void
    {
        $this->createBlock(['quantity' => 2, 'from_date' => '2026-12-10', 'to_date' => '2026-12-10'])->assertCreated();

        $this->createBlock(['quantity' => 1, 'from_date' => '2026-12-09', 'to_date' => '2026-12-11'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'inventory_unavailable')
            ->assertJsonPath('nights.0', ['date' => '2026-12-10', 'available' => 0]);

        $this->assertSame(1, InventoryBlock::query()->count());
    }

    public function test_blocks_cannot_start_in_the_past_or_go_beyond_the_horizon(): void
    {
        $this->createBlock(['from_date' => '2026-11-30', 'to_date' => '2026-12-02'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.message', 'Past nights cannot be changed: start on or after the business date.');

        $this->createBlock(['from_date' => '2027-01-20', 'to_date' => '2027-03-01'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'to_date');
    }

    public function test_a_room_type_of_another_property_cannot_be_blocked(): void
    {
        $elsewhere = RoomType::factory()->for($this->property())->create();

        $this->createBlock(['room_type_id' => $elsewhere->public_id])
            ->assertUnprocessable()->assertJsonPath('errors.0.field', 'room_type_id');
    }

    public function test_creating_a_block_needs_an_idempotency_key_and_replays_retries(): void
    {
        $payload = $this->blockPayload(['quantity' => 1]);

        $this->asRevenueManager()->postJson('/bff/inventory/blocks', $payload)
            ->assertStatus(400)->assertJsonPath('code', 'idempotency_key_required');

        $first = $this->asRevenueManager()->withHeader('Idempotency-Key', 'key-1')->postJson('/bff/inventory/blocks', $payload)->assertCreated();
        $retry = $this->asRevenueManager()->withHeader('Idempotency-Key', 'key-1')->postJson('/bff/inventory/blocks', $payload)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $retry->json('data.id'));
        $this->assertSame(1, InventoryBlock::query()->count());
        $this->assertSame(1, RoomTypeInventory::query()->where('room_type_id', $this->deluxe->id)->where('date', '2026-12-03')->value('blocked'));

        // Same key, different request: refused rather than silently replayed.
        $this->asRevenueManager()->withHeader('Idempotency-Key', 'key-1')->postJson('/bff/inventory/blocks', [...$payload, 'quantity' => 2])
            ->assertUnprocessable()->assertJsonPath('code', 'idempotency_key_reused');
    }

    public function test_releasing_a_block_puts_units_back_once(): void
    {
        $id = $this->createBlock(['quantity' => 2, 'from_date' => '2026-12-03', 'to_date' => '2026-12-04'])->json('data.id');

        $this->asRevenueManager()->postJson("/bff/inventory/blocks/{$id}/release")
            ->assertOk()
            ->assertJsonPath('data.status', 'released')
            ->assertJsonPath('data.released_by.name', fn ($name) => is_string($name));

        // Releasing twice changes nothing.
        $this->asRevenueManager()->postJson("/bff/inventory/blocks/{$id}/release")->assertOk();

        $this->assertSame([0, 0], RoomTypeInventory::query()->where('room_type_id', $this->deluxe->id)
            ->whereBetween('date', ['2026-12-03', '2026-12-04'])->orderBy('date')->pluck('blocked')->all());
    }

    public function test_the_block_list_shows_active_blocks_by_default(): void
    {
        $this->createBlock(['from_date' => '2026-12-03', 'to_date' => '2026-12-04', 'reason' => 'Keep']);
        $released = $this->createBlock(['from_date' => '2026-12-05', 'to_date' => '2026-12-05', 'reason' => 'Gone'])->json('data.id');
        $this->asRevenueManager()->postJson("/bff/inventory/blocks/{$released}/release");

        $this->asRevenueManager()->getJson('/bff/inventory/blocks')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reason', 'Keep');

        $this->asRevenueManager()->getJson('/bff/inventory/blocks?filter[status]=all')
            ->assertJsonCount(2, 'data');
    }

    public function test_expired_holds_are_released_by_the_scheduler(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 1)->setTime(9, 0));
        $this->createBlock(['type' => 'hold', 'quantity' => 1, 'from_date' => '2026-12-20', 'to_date' => '2026-12-22', 'expires_at' => '2026-12-01T12:00:00Z'])->assertCreated();
        $this->createBlock(['type' => 'owner', 'quantity' => 1, 'from_date' => '2026-12-20', 'to_date' => '2026-12-22'])->assertCreated();

        $this->artisan('inventory:release-expired-blocks')->expectsOutputToContain('Released 0 expired blocks.');

        $this->travelTo(now()->setTime(12, 5));
        $this->artisan('inventory:release-expired-blocks')->expectsOutputToContain('Released 1 expired block.');

        $this->assertSame(1, InventoryBlock::query()->whereNull('released_at')->count());
        $this->assertSame(1, RoomTypeInventory::query()->where('room_type_id', $this->deluxe->id)->where('date', '2026-12-21')->value('blocked'));
    }

    public function test_overbooking_is_set_for_a_range_and_audited(): void
    {
        $this->asRevenueManager()->putJson('/bff/inventory/overbooking', [
            'room_type_id' => $this->standard->public_id, 'from' => '2026-12-01', 'to' => '2026-12-07', 'allowance' => 1, 'weekdays' => [5, 6],
        ])->assertOk()->assertJsonPath('data.nights_changed', 2);

        $this->asRevenueManager()->getJson('/bff/inventory?from=2026-12-04&to=2026-12-04')
            ->assertJsonPath('data.rows.0.days.0.overbook_allowance', 1)
            ->assertJsonPath('data.rows.0.days.0.sellable', 5);

        $entry = AuditEntry::query()->where('event', 'overbooking_changed')->sole();
        $this->assertSame('Room type STD: overbooking allowance set to 1', $entry->description);
        $this->assertSame(2, $entry->properties['nights']);
    }

    public function test_inventory_needs_the_inventory_permission(): void
    {
        $this->actingAs($this->staff($this->hotel, Role::FrontDeskAgent))
            ->withHeader('X-Property-Id', $this->hotel->public_id)
            ->getJson('/bff/inventory?from=2026-12-01&to=2026-12-07')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function blockPayload(array $overrides = []): array
    {
        return [
            'room_type_id' => $this->deluxe->public_id,
            'type' => 'out_of_service',
            'quantity' => 1,
            'from_date' => '2026-12-03',
            'to_date' => '2026-12-04',
            'reason' => 'Carpet replacement',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBlock(array $overrides = []): TestResponse
    {
        return $this->asRevenueManager()
            ->withHeader('Idempotency-Key', bin2hex(random_bytes(8)))
            ->postJson('/bff/inventory/blocks', $this->blockPayload($overrides));
    }

    private function asRevenueManager(): static
    {
        return $this->actingAs($this->manager)
            ->withHeader('X-Property-Id', $this->hotel->public_id);
    }
}
