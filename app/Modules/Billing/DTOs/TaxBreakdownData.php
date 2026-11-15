<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use Spatie\LaravelData\Data;

/**
 * Taxes and fees on a room charge (spec §2.8). All amounts in minor units of `currency`.
 *
 * charge_minor = net_minor + inclusive_minor; total_minor = charge_minor + exclusive_minor.
 */
class TaxBreakdownData extends Data
{
    /**
     * @param  array<int, TaxLineData>  $lines  in the property's tax order
     */
    public function __construct(
        public string $currency,
        /** The room charge as priced (it already contains any inclusive taxes). */
        public int $charge_minor,
        /** The room charge without inclusive taxes. */
        public int $net_minor,
        public int $inclusive_minor,
        public int $exclusive_minor,
        /** What the guest pays. */
        public int $total_minor,
        public array $lines,
    ) {}
}
