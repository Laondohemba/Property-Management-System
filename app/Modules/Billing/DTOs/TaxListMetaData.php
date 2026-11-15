<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Support\Http\DTOs\OptionData;
use Spatie\LaravelData\Data;

class TaxListMetaData extends Data
{
    /**
     * @param  array<int, OptionData>  $calculations
     */
    public function __construct(
        /** The property's currency: fixed amounts are in its minor units. */
        public string $currency,
        public array $calculations,
    ) {}
}
