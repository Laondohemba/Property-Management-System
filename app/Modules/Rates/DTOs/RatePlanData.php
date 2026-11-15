<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use App\Modules\Billing\Models\Tax;
use App\Modules\Rates\Enums\MealPlan;
use App\Modules\Rates\Models\RatePlan;
use App\Modules\Rates\Models\RatePlanRoomType;
use App\Modules\Rates\Services\PolicyWording;
use App\Support\Http\DTOs\RecordStampsData;
use Spatie\LaravelData\Data;

/**
 * A rate plan (RTE-01, RTE-07). Amounts are minor units of `currency`.
 */
class RatePlanData extends Data
{
    /**
     * @param  array<int, RatePlanPriceData>  $prices  room types sold under the plan
     * @param  array<int, TaxRefData>  $taxes
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
        public ?string $description,
        public MealPlan $meal_plan,
        public string $meal_plan_label,
        public ?PolicyRefData $cancellation_policy,
        public ?PolicyRefData $deposit_policy,
        public ?int $min_price_minor,
        public ?int $max_price_minor,
        public int $sort_order,
        public bool $is_active,
        public string $currency,
        public array $prices,
        public array $taxes,
        public RecordStampsData $stamps,
    ) {}

    /**
     * Expects cancellationPolicy, depositPolicy, prices.roomType, taxes and the stamp relations loaded.
     */
    public static function fromModel(RatePlan $plan, string $currency, PolicyWording $wording): self
    {
        return new self(
            id: $plan->public_id,
            name: $plan->name,
            code: $plan->code,
            description: $plan->description,
            meal_plan: $plan->meal_plan,
            meal_plan_label: $plan->meal_plan->label(),
            cancellation_policy: $plan->cancellationPolicy === null ? null : new PolicyRefData(
                $plan->cancellationPolicy->public_id, $plan->cancellationPolicy->name, $wording->cancellation($plan->cancellationPolicy, $currency),
            ),
            deposit_policy: $plan->depositPolicy === null ? null : new PolicyRefData(
                $plan->depositPolicy->public_id, $plan->depositPolicy->name, $wording->deposit($plan->depositPolicy, $currency),
            ),
            min_price_minor: $plan->min_price_minor,
            max_price_minor: $plan->max_price_minor,
            sort_order: $plan->sort_order,
            is_active: $plan->is_active,
            currency: $currency,
            prices: $plan->prices
                ->sortBy(fn (RatePlanRoomType $price): string => sprintf('%06d %s', $price->roomType->sort_order, $price->roomType->name))
                ->map(fn (RatePlanRoomType $price): RatePlanPriceData => new RatePlanPriceData(
                    RoomTypeOptionData::fromModel($price->roomType),
                    $price->base_price_minor,
                    $price->extra_adult_minor,
                    $price->extra_child_minor,
                ))->values()->all(),
            taxes: $plan->taxes->map(fn (Tax $tax): TaxRefData => new TaxRefData($tax->public_id, $tax->name, $tax->code))->values()->all(),
            stamps: RecordStampsData::fromModel($plan),
        );
    }
}
