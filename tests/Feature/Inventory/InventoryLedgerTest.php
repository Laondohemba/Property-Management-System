<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Enums\Role;
use App\Modules\Inventory\Exceptions\InventoryUnavailable;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Inventory\Models\RoomTypeInventory;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Property\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * Master inventory (spec §6.3, §7.3, CHN-01): totals follow the active rooms, blocks and
 * overbooking stay within what is free, and past nights never change.
 */
class InventoryLedgerTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotel;

    private RoomType $deluxe;

    private InventoryLedger $ledger;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->hotel = $this->property(['business_date' => '2026-12-01']);
        $this->ledger = app(InventoryLedger::class);
        $this->today = CarbonImmutable::parse('2026-12-01');
        $this->deluxe = RoomType::factory()->for($this->hotel)->create(['code' => 'DLX']);
    }

    public function test_a_new_room_type_gets_rows_up_to_the_horizon(): void
    {
        $rows = RoomTypeInventory::query()->where('room_type_id', $this->deluxe->id)->orderBy('date')->get();

        $this->assertCount(60, $rows); // INVENTORY_HORIZON_DAYS in phpunit.xml
        $this->assertSame('2026-12-01', $rows->first()->date->toDateString());
        $this->assertSame('2027-01-29', $rows->last()->date->toDateString());
        $this->assertSame(0, $rows->first()->total);
    }

    public function test_totals_follow_active_rooms_from_the_business_date_on(): void
    {
        RoomTypeInventory::query()->insert([
            'organization_id' => $this->hotel->organization_id, 'property_id' => $this->hotel->id,
            'room_type_id' => $this->deluxe->id, 'date' => '2026-11-30', 'total' => 0,
        ]);

        $rooms = Room::factory()->count(3)->for($this->deluxe)->create();
        $this->assertSame([3], $this->totals('2026-12-01', '2027-01-29'));

        $rooms[0]->update(['is_active' => false]);
        $rooms[1]->delete();
        $this->assertSame([1], $this->totals('2026-12-01', '2027-01-29'));

        // History is never rewritten.
        $this->assertSame([0], $this->totals('2026-11-30', '2026-11-30'));
    }

    public function test_moving_a_room_to_another_type_moves_its_unit(): void
    {
        $standard = RoomType::factory()->for($this->hotel)->create(['code' => 'STD']);
        $room = Room::factory()->for($this->deluxe)->create();

        $room->update(['room_type_id' => $standard->id]);

        $this->assertSame([0], $this->totals('2026-12-01', '2026-12-05'));
        $this->assertSame([1], $this->totals('2026-12-01', '2026-12-05', $standard));
    }

    public function test_rooms_cannot_be_deactivated_below_what_is_blocked(): void
    {
        $rooms = Room::factory()->count(2)->for($this->deluxe)->create();
        $this->ledger->block($this->deluxe, $this->today->addDays(3), $this->today->addDays(4), 2);

        try {
            DB::transaction(fn () => $rooms[0]->update(['is_active' => false]));
            $this->fail('Expected InventoryUnavailable.');
        } catch (InventoryUnavailable $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame(['2026-12-04', '2026-12-05'], array_column($e->extensions['nights'], 'date'));
        }

        // The room change was rolled back together with the refused inventory update.
        $this->assertTrue($rooms[0]->fresh()->is_active);
        $this->assertSame([2], $this->totals('2026-12-01', '2026-12-10'));
    }

    public function test_a_block_takes_units_off_every_night_of_the_range(): void
    {
        Room::factory()->count(5)->for($this->deluxe)->create();

        $this->ledger->block($this->deluxe, $this->today->addDays(2), $this->today->addDays(4), 2);

        $rows = $this->rows('2026-12-02', '2026-12-06');
        $this->assertSame([0, 2, 2, 2, 0], $rows->pluck('blocked')->all());
        $this->assertSame([5, 3, 3, 3, 5], $rows->map->available()->all());
        $this->assertTrue($rows->every(fn (RoomTypeInventory $row) => $row->version > 0 || $row->blocked === 0));
    }

    public function test_a_block_larger_than_what_is_free_on_any_night_is_refused_whole(): void
    {
        Room::factory()->count(3)->for($this->deluxe)->create();
        $this->ledger->block($this->deluxe, $this->today->addDays(5), $this->today->addDays(5), 2);

        try {
            $this->ledger->block($this->deluxe, $this->today->addDays(4), $this->today->addDays(6), 2);
            $this->fail('Expected InventoryUnavailable.');
        } catch (InventoryUnavailable $e) {
            $this->assertSame([['date' => '2026-12-06', 'available' => 1]], $e->extensions['nights']);
        }

        // Nothing changed on the nights that did have room.
        $this->assertSame([0, 2, 0], $this->rows('2026-12-05', '2026-12-07')->pluck('blocked')->all());
    }

    public function test_releasing_puts_units_back_and_never_goes_below_zero(): void
    {
        Room::factory()->count(3)->for($this->deluxe)->create();
        $this->ledger->block($this->deluxe, $this->today, $this->today->addDay(), 2);

        $this->ledger->release($this->deluxe, $this->today, $this->today->addDay(), 2);
        $this->ledger->release($this->deluxe, $this->today, $this->today->addDay(), 2);

        $this->assertSame([0, 0], $this->rows('2026-12-01', '2026-12-02')->pluck('blocked')->all());
    }

    public function test_overbooking_can_be_set_for_chosen_weekdays(): void
    {
        Room::factory()->count(2)->for($this->deluxe)->create();

        // 2026-12-04 is a Friday, 2026-12-05 a Saturday.
        $changed = $this->ledger->setOverbooking($this->deluxe, $this->today, $this->today->addDays(6), 1, [5, 6]);

        $this->assertSame(2, $changed);
        $rows = $this->rows('2026-12-01', '2026-12-07')->keyBy(fn ($row) => $row->date->toDateString());
        $this->assertSame(1, $rows['2026-12-04']->overbook_allowance);
        $this->assertSame(1, $rows['2026-12-05']->overbook_allowance);
        $this->assertSame(0, $rows['2026-12-03']->overbook_allowance);
        $this->assertSame(3, $rows['2026-12-04']->sellable());
    }

    public function test_overbooking_cannot_be_lowered_below_what_is_already_used(): void
    {
        Room::factory()->count(1)->for($this->deluxe)->create();
        $this->ledger->setOverbooking($this->deluxe, $this->today, $this->today, 1);
        RoomTypeInventory::query()->where('room_type_id', $this->deluxe->id)->where('date', '2026-12-01')->update(['sold' => 2]);

        $this->expectException(InventoryUnavailable::class);
        $this->ledger->setOverbooking($this->deluxe, $this->today, $this->today, 0);
    }

    public function test_the_database_refuses_more_sold_and_blocked_than_total_plus_allowance(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('The check constraint is enforced by MySQL 8 (CI and production).');
        }

        Room::factory()->count(1)->for($this->deluxe)->create();

        $this->expectException(QueryException::class);
        RoomTypeInventory::query()->where('room_type_id', $this->deluxe->id)->where('date', '2026-12-01')->update(['blocked' => 2]);
    }

    public function test_extend_is_idempotent_and_fills_gaps(): void
    {
        RoomTypeInventory::query()->where('room_type_id', $this->deluxe->id)->where('date', '>=', '2027-01-20')->delete();

        $this->assertSame(10, $this->ledger->extend($this->deluxe));
        $this->assertSame(0, $this->ledger->extend($this->deluxe));

        $this->artisan('inventory:extend')->expectsOutputToContain('0 rows created')->assertSuccessful();
    }

    public function test_staff_moving_rooms_through_the_bff_see_the_refusal_as_409(): void
    {
        $room = Room::factory()->for($this->deluxe)->create();
        $this->ledger->block($this->deluxe, $this->today, $this->today, 1);

        $this->actingAs($this->staff($this->hotel, Role::PropertyManager))
            ->withHeader('X-Property-Id', $this->hotel->public_id)
            ->patchJson("/bff/settings/rooms/{$room->public_id}", ['is_active' => false])
            ->assertStatus(409)
            ->assertJsonPath('code', 'inventory_unavailable')
            ->assertJsonPath('nights.0.date', '2026-12-01');

        $this->assertTrue($room->fresh()->is_active);
    }

    /**
     * @return list<int> distinct totals over the range
     */
    private function totals(string $from, string $to, ?RoomType $roomType = null): array
    {
        return array_values(array_unique($this->rows($from, $to, $roomType)->pluck('total')->all()));
    }

    /**
     * @return Collection<int, RoomTypeInventory>
     */
    private function rows(string $from, string $to, ?RoomType $roomType = null)
    {
        return RoomTypeInventory::query()
            ->where('room_type_id', ($roomType ?? $this->deluxe)->id)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();
    }
}
