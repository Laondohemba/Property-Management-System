<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use Spatie\LaravelData\Data;

/**
 * One tax or fee in a price breakdown.
 */
class TaxLineData extends Data
{
    public function __construct(
        public string $tax_id,
        public string $name,
        public string $code,
        /** Human description of how it was worked out, e.g. "7.5% included" or "₦500 × 2 nights". */
        public string $basis,
        public int $amount_minor,
        /** Included in the room price (not added on top). */
        public bool $is_inclusive,
    ) {}
}
