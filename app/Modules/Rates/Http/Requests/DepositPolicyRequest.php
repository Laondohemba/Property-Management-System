<?php

declare(strict_types=1);

namespace App\Modules\Rates\Http\Requests;

use App\Modules\Rates\Enums\BalanceDue;
use App\Modules\Rates\Enums\DepositDue;
use App\Modules\Rates\Enums\DepositType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deposit and payment terms (RTE-07). Only the values matching the chosen types are kept.
 */
class DepositPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'guest_text' => is_string($this->input('guest_text')) && trim($this->input('guest_text')) !== '' ? trim($this->input('guest_text')) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'deposit_type' => ['required', Rule::enum(DepositType::class)],
            'deposit_percent' => ['nullable', 'required_if:deposit_type,percent', 'numeric', 'decimal:0,4', 'gt:0', 'max:100'],
            'deposit_nights' => ['nullable', 'required_if:deposit_type,nights', 'integer', 'min:1', 'max:30'],
            'deposit_amount_minor' => ['nullable', 'required_if:deposit_type,fixed', 'integer', 'min:1', 'max:100000000000'],
            'deposit_due' => ['required', Rule::enum(DepositDue::class)],
            'due_days_before_arrival' => ['nullable', 'required_if:deposit_due,before_arrival', 'integer', 'min:1', 'max:365'],
            'requires_card_guarantee' => ['required', 'boolean'],
            'balance_due' => ['required', Rule::enum(BalanceDue::class)],
            'guest_text' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'deposit_percent.required_if' => 'Enter the percentage.',
            'deposit_nights.required_if' => 'Enter how many nights.',
            'deposit_amount_minor.required_if' => 'Enter the amount.',
            'due_days_before_arrival.required_if' => 'Enter how many days before arrival.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        $data = $this->validated();
        $type = DepositType::from((string) $data['deposit_type']);

        $data['deposit_percent'] = $type === DepositType::Percent ? (string) $data['deposit_percent'] : null;
        $data['deposit_nights'] = $type === DepositType::Nights ? (int) $data['deposit_nights'] : null;
        $data['deposit_amount_minor'] = $type === DepositType::Fixed ? (int) $data['deposit_amount_minor'] : null;
        $data['due_days_before_arrival'] = $type !== DepositType::None && $data['deposit_due'] === DepositDue::BeforeArrival->value
            ? (int) $data['due_days_before_arrival']
            : null;

        return $data;
    }
}
