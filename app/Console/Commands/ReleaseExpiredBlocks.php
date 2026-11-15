<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Inventory\Actions\ManageInventory;
use App\Modules\Inventory\Models\InventoryBlock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Puts the units of blocks past their expiry back on sale (CAL-06: holds with expiry).
 * Safe to run on any schedule and twice in a row: releasing is idempotent.
 */
#[Signature('inventory:release-expired-blocks')]
#[Description('Release inventory blocks whose expiry has passed')]
class ReleaseExpiredBlocks extends Command
{
    public function handle(ManageInventory $inventory): int
    {
        $released = 0;

        InventoryBlock::query()->withoutGlobalScopes()
            ->active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (InventoryBlock $block) use ($inventory, &$released): void {
                $inventory->release(null, $block);
                $released++;
            });

        $this->info("Released {$released} expired ".($released === 1 ? 'block' : 'blocks').'.');

        return self::SUCCESS;
    }
}
