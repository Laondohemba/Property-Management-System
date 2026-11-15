<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use Spatie\LaravelData\Data;

/**
 * Inventory screen (GET /bff/inventory): room types × nights, plus a totals row.
 */
class InventoryGridData extends Data
{
    /**
     * @param  array<int, string>  $dates  YYYY-MM-DD, from `from` to `to`
     * @param  array<int, InventoryRowData>  $rows
     * @param  array<int, InventoryDayData>  $totals  all room types added up, per date
     */
    public function __construct(
        public string $from,
        public string $to,
        /** The business date: earlier nights can no longer change. */
        public string $first_date,
        /** Last night kept in the ledger. */
        public string $last_date,
        public array $dates,
        public array $rows,
        public array $totals,
    ) {}
}
