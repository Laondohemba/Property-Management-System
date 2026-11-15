<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Inventory\Models\RoomType;
use App\Modules\Inventory\Services\InventoryLedger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Keeps room_type_inventory filled up to the horizon for every room type of every property
 * (CHN-01). Idempotent: run daily by the scheduler, and once after deploying the ledger to
 * backfill existing room types.
 */
#[Signature('inventory:extend')]
#[Description('Create missing inventory rows up to the horizon for every room type')]
class ExtendInventory extends Command
{
    public function handle(InventoryLedger $ledger): int
    {
        $created = 0;
        $roomTypes = 0;

        RoomType::query()->withoutGlobalScopes()->orderBy('id')->each(function (RoomType $roomType) use ($ledger, &$created, &$roomTypes): void {
            $created += $ledger->extend($roomType);
            $roomTypes++;
        });

        $this->info("Inventory extended: {$created} rows created across {$roomTypes} room types.");

        return self::SUCCESS;
    }
}
