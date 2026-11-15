<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InventoryUnavailable;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Inventory\Models\RoomTypeInventory;
use App\Modules\Property\Models\Property;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only writer of room_type_inventory (spec §6.3, §7.3, §7.4, CHN-01).
 *
 * Every change runs in a database transaction on the primary with the affected rows locked
 * in ascending date order, so concurrent requests on different pods queue up behind each
 * other instead of overselling, and cannot deadlock. Correctness rests on these locks and on
 * the table's check constraint, never on in-process state.
 *
 * Availability = total − blocked − sold. `total` follows the number of active rooms of the
 * room type from the business date onwards; past nights are history and are never changed.
 */
final class InventoryLedger
{
    /** First night that can still change: the property's business date. */
    public function firstDate(Property $property): CarbonImmutable
    {
        return $property->business_date !== null
            ? CarbonImmutable::parse($property->business_date->toDateString())
            : CarbonImmutable::now($property->timezone ?: 'UTC')->startOfDay();
    }

    /** Last night kept in the ledger (config pms.inventory.horizon_days). */
    public function lastDate(Property $property): CarbonImmutable
    {
        return $this->firstDate($property)->addDays(max(1, (int) config('pms.inventory.horizon_days')) - 1);
    }

    /**
     * Create any missing rows from the business date to the horizon. Idempotent; run on room
     * type creation and daily by `inventory:extend`.
     *
     * @return int rows created
     */
    public function extend(RoomType $roomType): int
    {
        $property = $this->propertyOf($roomType);

        return $this->createMissingRows($roomType, $property, $this->firstDate($property), $this->lastDate($property));
    }

    /**
     * Follow a change in the number of active rooms (room added, moved, activated, deactivated
     * or deleted). Refuses when fewer rooms would remain than are already sold or blocked.
     */
    public function syncTotal(RoomType $roomType): void
    {
        $property = $this->propertyOf($roomType);

        DB::transaction(function () use ($roomType, $property): void {
            $total = $this->activeRooms($roomType);
            $from = $this->firstDate($property)->toDateString();

            $conflicts = RoomTypeInventory::query()
                ->where('room_type_id', $roomType->id)
                ->where('date', '>=', $from)
                ->whereRaw('sold + blocked > ? + overbook_allowance', [$total])
                ->orderBy('date')
                ->lockForUpdate()
                ->get();

            if ($conflicts->isNotEmpty()) {
                throw InventoryUnavailable::forNights(
                    "Only {$total} active ".($total === 1 ? 'room' : 'rooms')." of {$roomType->code} would remain, but more are already sold or blocked on {$conflicts->count()} ".($conflicts->count() === 1 ? 'night' : 'nights').'. Release blocks or move bookings first.',
                    $this->describe($conflicts),
                );
            }

            RoomTypeInventory::query()
                ->where('room_type_id', $roomType->id)
                ->where('date', '>=', $from)
                ->where('total', '!=', $total)
                ->update(['total' => $total, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);

            $this->createMissingRows($roomType, $property, $this->firstDate($property), $this->lastDate($property), $total);
        });
    }

    /**
     * Take `quantity` units off sale on every night from $from to $to (inclusive).
     *
     * @throws InventoryUnavailable when any night has fewer units free
     */
    public function block(RoomType $roomType, CarbonImmutable $from, CarbonImmutable $to, int $quantity): void
    {
        DB::transaction(function () use ($roomType, $from, $to, $quantity): void {
            $rows = $this->lockRange($roomType, $from, $to);
            $short = $rows->filter(fn (RoomTypeInventory $row): bool => $row->available() < $quantity);

            if ($short->isNotEmpty()) {
                throw InventoryUnavailable::forNights(
                    "{$quantity} ".($quantity === 1 ? 'unit is' : 'units are')." not free on every night: {$short->count()} ".($short->count() === 1 ? 'night has' : 'nights have').' fewer.',
                    $this->describe($short),
                );
            }

            $this->adjust($rows, 'blocked', $quantity);
        });
    }

    /** Put blocked units back on sale. */
    public function release(RoomType $roomType, CarbonImmutable $from, CarbonImmutable $to, int $quantity): void
    {
        DB::transaction(function () use ($roomType, $from, $to, $quantity): void {
            $this->adjust($this->lockRange($roomType, $from, $to), 'blocked', -$quantity);
        });
    }

    /**
     * Set how many units may be oversold on the matching nights (spec §7.3).
     *
     * @param  list<int>|null  $weekdays  ISO days (1 = Monday … 7 = Sunday); null for every day
     * @return int nights changed
     */
    public function setOverbooking(RoomType $roomType, CarbonImmutable $from, CarbonImmutable $to, int $allowance, ?array $weekdays = null): int
    {
        return DB::transaction(function () use ($roomType, $from, $to, $allowance, $weekdays): int {
            $rows = $this->lockRange($roomType, $from, $to)
                ->filter(fn (RoomTypeInventory $row): bool => $weekdays === null || in_array($row->date->dayOfWeekIso, $weekdays, true));

            $short = $rows->filter(fn (RoomTypeInventory $row): bool => $row->sold + $row->blocked > $row->total + $allowance);

            if ($short->isNotEmpty()) {
                throw InventoryUnavailable::forNights(
                    "More units are already sold or blocked than an allowance of {$allowance} permits on {$short->count()} ".($short->count() === 1 ? 'night' : 'nights').'.',
                    $this->describe($short),
                );
            }

            $changed = $rows->filter(fn (RoomTypeInventory $row): bool => $row->overbook_allowance !== $allowance);

            if ($changed->isNotEmpty()) {
                RoomTypeInventory::query()->whereKey($changed->modelKeys())->update([
                    'overbook_allowance' => $allowance,
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);
            }

            return $changed->count();
        });
    }

    public function activeRooms(RoomType $roomType): int
    {
        return Room::query()->withoutGlobalScopes()
            ->where('room_type_id', $roomType->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Rows for every night in the range, created if missing, locked FOR UPDATE in ascending
     * date order. Must run inside a transaction.
     *
     * @return Collection<int, RoomTypeInventory>
     */
    private function lockRange(RoomType $roomType, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Inventory rows can only be locked inside a transaction.');
        }

        $this->createMissingRows($roomType, $this->propertyOf($roomType), $from, $to);

        $rows = RoomTypeInventory::query()->withoutGlobalScopes()
            ->where('room_type_id', $roomType->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->lockForUpdate()
            ->get();

        $nights = (int) $from->diffInDays($to) + 1;
        if ($rows->count() !== $nights) {
            throw new LogicException("Expected {$nights} inventory rows for room type {$roomType->id}, found {$rows->count()}.");
        }

        return $rows;
    }

    /**
     * @param  Collection<int, RoomTypeInventory>  $rows  locked rows
     * @param  'blocked'|'sold'  $column
     */
    private function adjust(Collection $rows, string $column, int $by): void
    {
        $extra = ['version' => DB::raw('version + 1'), 'updated_at' => now()];
        $query = fn () => RoomTypeInventory::query()->withoutGlobalScopes()->whereKey($rows->modelKeys());

        if ($by >= 0) {
            $query()->increment($column, $by, $extra);

            return;
        }

        // Never below zero (the column is unsigned): rows holding fewer go to zero first, then
        // the rest are decremented.
        $amount = abs($by);
        $query()->where($column, '<', $amount)->update([$column => 0, ...$extra]);
        $query()->where($column, '>=', $amount)->decrement($column, $amount, $extra);
    }

    private function createMissingRows(RoomType $roomType, Property $property, CarbonImmutable $from, CarbonImmutable $to, ?int $total = null): int
    {
        $existing = RoomTypeInventory::query()->withoutGlobalScopes()
            ->where('room_type_id', $roomType->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
            ->flip();

        $total ??= $this->activeRooms($roomType);
        $now = now();
        $rows = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $day = $date->toDateString();
            if (! $existing->has($day)) {
                $rows[] = [
                    'organization_id' => $property->organization_id,
                    'property_id' => $property->id,
                    'room_type_id' => $roomType->id,
                    'date' => $day,
                    'total' => $total,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            // Ignore rows another request created a moment ago (unique room_type_id + date).
            RoomTypeInventory::query()->withoutGlobalScopes()->insertOrIgnore($chunk);
        }

        return count($rows);
    }

    private function propertyOf(RoomType $roomType): Property
    {
        return Property::query()->withoutGlobalScopes()->findOrFail($roomType->property_id);
    }

    /**
     * @param  Collection<int, RoomTypeInventory>  $rows
     * @return list<array{date: string, available: int}>
     */
    private function describe(Collection $rows): array
    {
        return array_values($rows->map(fn (RoomTypeInventory $row): array => [
            'date' => $row->date->toDateString(),
            'available' => $row->available(),
        ])->all());
    }
}
