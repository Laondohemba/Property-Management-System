<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use App\Support\Http\DTOs\OptionData;
use Spatie\LaravelData\Data;

class PoliciesMetaData extends Data
{
    /**
     * @param  array<int, OptionData>  $penalty_types
     * @param  array<int, OptionData>  $deposit_types
     * @param  array<int, OptionData>  $deposit_dues
     * @param  array<int, OptionData>  $balance_dues
     */
    public function __construct(
        public string $currency,
        public array $penalty_types,
        public array $deposit_types,
        public array $deposit_dues,
        public array $balance_dues,
    ) {}
}
