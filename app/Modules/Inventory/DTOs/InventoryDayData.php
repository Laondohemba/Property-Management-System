<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use Spatie\LaravelData\Data;

/**
 * One room type (or the property total) on one night.
 * available = total − blocked − sold (negative when overbooked); sellable adds the allowance.
 */
class InventoryDayData extends Data
{
    public function __construct(
        public string $date,
        public int $total,
        public int $blocked,
        public int $sold,
        public int $overbook_allowance,
        public int $available,
        public int $sellable,
    ) {}

    public static function of(string $date, int $total, int $blocked, int $sold, int $overbookAllowance): self
    {
        $available = $total - $blocked - $sold;

        return new self($date, $total, $blocked, $sold, $overbookAllowance, $available, $available + $overbookAllowance);
    }
}
