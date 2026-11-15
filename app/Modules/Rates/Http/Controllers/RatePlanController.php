<?php

declare(strict_types=1);

namespace App\Modules\Rates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\DTOs\TaxData;
use App\Modules\Billing\Models\Tax;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Rates\Actions\SaveRatePlan;
use App\Modules\Rates\DTOs\PolicyRefData;
use App\Modules\Rates\DTOs\RatePlanData;
use App\Modules\Rates\DTOs\RatePlanOptionsData;
use App\Modules\Rates\DTOs\RoomTypeOptionData;
use App\Modules\Rates\Enums\MealPlan;
use App\Modules\Rates\Http\Requests\PricePreviewRequest;
use App\Modules\Rates\Http\Requests\RatePlanRequest;
use App\Modules\Rates\Models\CancellationPolicy;
use App\Modules\Rates\Models\DepositPolicy;
use App\Modules\Rates\Models\RatePlan;
use App\Modules\Rates\Services\PolicyWording;
use App\Modules\Rates\Services\RatePricing;
use App\Support\Http\Bff;
use App\Support\Http\DTOs\OptionData;
use App\Support\Http\DTOs\PageMetaData;
use App\Support\Http\Paginated;
use App\Support\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * /bff/rate-plans (RTE-01, RTE-07, spec §8.2).
 */
class RatePlanController extends Controller implements HasMiddleware
{
    private const RELATIONS = ['cancellationPolicy', 'depositPolicy', 'prices.roomType', 'taxes'];

    public function __construct(
        private readonly PropertyContext $context,
        private readonly PolicyWording $wording,
    ) {}

    public static function middleware(): array
    {
        return [new Middleware('can:'.Permission::RatesManage->value)];
    }

    public function index(Request $request): JsonResponse
    {
        $property = $this->context->require();

        $plans = RatePlan::forProperty($property)
            ->with([...self::RELATIONS, ...RatePlan::stampRelations()])
            ->when($request->has('filter.active'), fn ($query) => $query->where('is_active', $request->boolean('filter.active')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(Paginated::perPage($request));

        return Bff::respond(
            $plans->getCollection()->map(fn (RatePlan $plan): RatePlanData => RatePlanData::fromModel($plan, $property->currency, $this->wording))->all(),
            PageMetaData::fromPaginator($plans),
        );
    }

    /**
     * Choices for the rate plan form: room types, active taxes and policies, meal plans.
     */
    public function options(): JsonResponse
    {
        $property = $this->context->require();
        $currency = $property->currency;

        return Bff::respond(new RatePlanOptionsData(
            currency: $currency,
            meal_plans: OptionData::fromEnum(MealPlan::class),
            room_types: RoomType::forProperty($property)->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (RoomType $roomType): RoomTypeOptionData => RoomTypeOptionData::fromModel($roomType))->all(),
            taxes: Tax::forProperty($property)->with(Tax::stampRelations())->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (Tax $tax): TaxData => TaxData::fromModel($tax))->all(),
            cancellation_policies: CancellationPolicy::forProperty($property)->where('is_active', true)->orderBy('name')->get()
                ->map(fn (CancellationPolicy $policy): PolicyRefData => new PolicyRefData($policy->public_id, $policy->name, $this->wording->cancellation($policy, $currency)))->all(),
            deposit_policies: DepositPolicy::forProperty($property)->where('is_active', true)->orderBy('name')->get()
                ->map(fn (DepositPolicy $policy): PolicyRefData => new PolicyRefData($policy->public_id, $policy->name, $this->wording->deposit($policy, $currency)))->all(),
        ));
    }

    public function store(RatePlanRequest $request, SaveRatePlan $save): JsonResponse
    {
        $property = $this->context->require();
        $plan = $save->handle($this->actor($request), $property, $request->validated());

        return Bff::respond(RatePlanData::fromModel($plan, $property->currency, $this->wording), status: 201);
    }

    public function show(RatePlan $ratePlan): JsonResponse
    {
        $this->context->ensureOwns($ratePlan);

        return Bff::respond(RatePlanData::fromModel(
            $ratePlan->load([...self::RELATIONS, ...RatePlan::stampRelations()]),
            $this->context->require()->currency,
            $this->wording,
        ));
    }

    public function update(RatePlanRequest $request, RatePlan $ratePlan, SaveRatePlan $save): JsonResponse
    {
        $this->context->ensureOwns($ratePlan);
        $property = $this->context->require();

        $plan = $save->handle($this->actor($request), $property, $request->validated(), $ratePlan);

        return Bff::respond(RatePlanData::fromModel($plan, $property->currency, $this->wording));
    }

    /**
     * GET /bff/rate-plans/{ratePlan}/preview: what a stay costs at the plan's base prices.
     */
    public function preview(PricePreviewRequest $request, RatePlan $ratePlan, RatePricing $pricing): JsonResponse
    {
        $this->context->ensureOwns($ratePlan);

        return Bff::respond($pricing->preview(
            $ratePlan->load(['taxes', 'cancellationPolicy', 'depositPolicy']),
            $request->roomType(),
            (int) $request->validated('adults'),
            (int) ($request->validated('children') ?? 0),
            (int) ($request->validated('nights') ?? 1),
            $this->context->require()->currency,
        ));
    }
}
