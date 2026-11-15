<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\TaxCalculation;
use App\Modules\Billing\Models\Tax;
use App\Support\PropertyContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A tax or fee (spec §2.8). A percentage needs `rate` and may be inclusive; fixed fees need
 * `amount_minor` (property currency) and are always added on top.
 */
class TaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'code'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper((string) $this->input('code'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Tax|null $tax */
        $tax = $this->route('tax');
        // Create and update both send the whole tax, so the fields always agree with each other.
        $required = 'required';

        return [
            'name' => [$required, 'string', 'max:100'],
            'code' => [
                $required, 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('taxes', 'code')->where('property_id', app(PropertyContext::class)->require()->id)->ignore($tax?->id),
            ],
            'calculation' => [$required, Rule::enum(TaxCalculation::class)],
            'rate' => ['nullable', 'required_if:calculation,percentage', 'numeric', 'decimal:0,4', 'gt:0', 'max:100'],
            'amount_minor' => ['nullable', 'required_unless:calculation,percentage', 'integer', 'min:1', 'max:100000000000'],
            'is_inclusive' => ['sometimes', 'boolean', 'declined_if:calculation,per_night,per_stay,per_person_night'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.required_if' => 'Enter the percentage.',
            'amount_minor.required_unless' => 'Enter the amount.',
            'is_inclusive.declined_if' => 'Only percentages can be included in the price.',
            'code.regex' => 'Use letters, numbers, dashes or underscores.',
        ];
    }
}
