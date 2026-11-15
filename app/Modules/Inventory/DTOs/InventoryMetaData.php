<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use Spatie\LaravelData\Data;

class InventoryMetaData extends Data
{
    /**
     * @param  array<int, BlockTypeOptionData>  $block_types
     */
    public function __construct(
        public array $block_types,
    ) {}
}
