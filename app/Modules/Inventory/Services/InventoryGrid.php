<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\DTOs\InventoryDayData;
use App\Modules\Inventory\DTOs\InventoryGridData;
use App\Modules\Inventory\DTOs\InventoryRowData;
use App\Modules\Inventory\DTOs\RoomTypeRefData;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Inventory\Models\RoomTypeInventory;
use App\Modules\Property\Models\Property;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Builds the inventory screen from the ledger in one query (read-only: nights not yet in the
 * ledger are shown with the current room count, without writing anything).
 */
final class InventoryGrid
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    public function build(Property $property, CarbonImmutable $from, CarbonImmutable $to): InventoryGridData
    {
        $dates = array_map(
            fn ($date): string => $date->toDateString(),
            iterator_to_array(CarbonPeriod::create($from, $to)),
        );

        $roomTypes = RoomType::forProperty($property)->orderBy('sort_order')->orderBy('name')->get();

        $activeRooms = DB::table('rooms')
            ->where('property_id', $property->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->groupBy('room_type_id')
            ->pluck(DB::raw('count(*)'), 'room_type_id');

        /** @var array<int, array<string, RoomTypeInventory>> $ledger room type id => date => row */
        $ledger = [];
        RoomTypeInventory::forProperty($property)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->each(function (RoomTypeInventory $row) use (&$ledger): void {
                $ledger[$row->room_type_id][$row->date->toDateString()] = $row;
            });

        // Per-date sums for the totals row.
        $total = $blocked = $sold = $allowance = array_fill_keys($dates, 0);

        $rows = $roomTypes->map(function (RoomType $roomType) use ($dates, $ledger, $activeRooms, &$total, &$blocked, &$sold, &$allowance): InventoryRowData {
            $days = array_map(function (string $date) use ($roomType, $ledger, $activeRooms, &$total, &$blocked, &$sold, &$allowance): InventoryDayData {
                $row = $ledger[$roomType->id][$date] ?? null;
                $day = $row !== null
                    ? InventoryDayData::of($date, $row->total, $row->blocked, $row->sold, $row->overbook_allowance)
                    : InventoryDayData::of($date, (int) ($activeRooms[$roomType->id] ?? 0), 0, 0, 0);

                $total[$date] += $day->total;
                $blocked[$date] += $day->blocked;
                $sold[$date] += $day->sold;
                $allowance[$date] += $day->overbook_allowance;

                return $day;
            }, $dates);

            return new InventoryRowData(
                new RoomTypeRefData($roomType->public_id, $roomType->name, $roomType->code),
                $roomType->is_active,
                $days,
            );
        })->values()->all();

        return new InventoryGridData(
            from: $from->toDateString(),
            to: $to->toDateString(),
            first_date: $this->ledger->firstDate($property)->toDateString(),
            last_date: $this->ledger->lastDate($property)->toDateString(),
            dates: $dates,
            rows: $rows,
            totals: array_map(
                fn (string $date): InventoryDayData => InventoryDayData::of($date, $total[$date], $blocked[$date], $sold[$date], $allowance[$date]),
                $dates,
            ),
        );
    }
}
