<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use App\Modules\Billing\DTOs\TaxData;
use App\Support\Http\DTOs\OptionData;
use Spatie\LaravelData\Data;

/** Choices for the rate plan form (GET /bff/rate-plans/options). */
class RatePlanOptionsData extends Data
{
    /**
     * @param  array<int, OptionData>  $meal_plans
     * @param  array<int, RoomTypeOptionData>  $room_types
     * @param  array<int, TaxData>  $taxes  active taxes of the property
     * @param  array<int, PolicyRefData>  $cancellation_policies  active ones
     * @param  array<int, PolicyRefData>  $deposit_policies  active ones
     */
    public function __construct(
        public string $currency,
        public array $meal_plans,
        public array $room_types,
        public array $taxes,
        public array $cancellation_policies,
        public array $deposit_policies,
    ) {}
}
