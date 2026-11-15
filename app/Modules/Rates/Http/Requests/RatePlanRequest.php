<?php

declare(strict_types=1);

namespace App\Modules\Rates\Http\Requests;

use App\Modules\Rates\Enums\MealPlan;
use App\Modules\Rates\Models\RatePlan;
use App\Support\PropertyContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A rate plan with its prices per room type, taxes and policies (RTE-01, RTE-07). The whole
 * plan is sent on create and update; `change_reason` is recorded with price changes (RTE-09).
 */
class RatePlanRequest extends FormRequest
{
    public const MAX_MINOR = 100_000_000_000;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['name', 'description', 'change_reason'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field)) === '' ? null : trim($this->input($field));
            }
        }
        if (is_string($this->input('code'))) {
            $merge['code'] = strtoupper(trim($this->input('code')));
        }
        $this->merge($merge);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $propertyId = app(PropertyContext::class)->require()->id;
        /** @var RatePlan|null $plan */
        $plan = $this->route('ratePlan');
        $owned = fn (string $table) => Rule::exists($table, 'public_id')->where('property_id', $propertyId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('rate_plans', 'code')->where('property_id', $propertyId)->ignore($plan?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'meal_plan' => ['required', Rule::enum(MealPlan::class)],
            'cancellation_policy_id' => ['nullable', 'string', $owned('cancellation_policies')],
            'deposit_policy_id' => ['nullable', 'string', $owned('deposit_policies')],
            'min_price_minor' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MINOR],
            'max_price_minor' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MINOR, 'gte:min_price_minor'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
            'tax_ids' => ['present', 'array'],
            'tax_ids.*' => ['string', 'distinct', $owned('taxes')],
            'prices' => ['present', 'array', 'max:100'],
            'prices.*.room_type_id' => ['required', 'string', 'distinct', Rule::exists('room_types', 'public_id')->where('property_id', $propertyId)->whereNull('deleted_at')],
            'prices.*.base_price_minor' => ['required', 'integer', 'min:0', 'max:'.self::MAX_MINOR],
            'prices.*.extra_adult_minor' => ['required', 'integer', 'min:0', 'max:'.self::MAX_MINOR],
            'prices.*.extra_child_minor' => ['required', 'integer', 'min:0', 'max:'.self::MAX_MINOR],
            'change_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, dashes or underscores.',
            'max_price_minor.gte' => 'The maximum must not be below the minimum.',
            'prices.*.room_type_id.distinct' => 'Each room type can be priced once.',
        ];
    }
}
