<?php

declare(strict_types=1);

namespace App\Modules\Rates\Http\Requests;

use App\Modules\Rates\Enums\PenaltyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cancellation and no-show terms (RTE-07). Only the value matching each penalty type is kept.
 */
class CancellationPolicyRequest extends FormRequest
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
            'free_cancellation_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            ...$this->penaltyRules('penalty'),
            ...$this->penaltyRules('no_show'),
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
            'penalty_nights.required_if' => 'Enter how many nights are charged.',
            'penalty_percent.required_if' => 'Enter the percentage charged.',
            'penalty_amount_minor.required_if' => 'Enter the amount charged.',
            'no_show_nights.required_if' => 'Enter how many nights are charged.',
            'no_show_percent.required_if' => 'Enter the percentage charged.',
            'no_show_amount_minor.required_if' => 'Enter the amount charged.',
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function penaltyRules(string $prefix): array
    {
        $type = $prefix === 'penalty' ? 'penalty_type' : 'no_show_type';

        return [
            $type => ['required', Rule::enum(PenaltyType::class)],
            "{$prefix}_nights" => ['nullable', "required_if:{$type},nights", 'integer', 'min:1', 'max:30'],
            "{$prefix}_percent" => ['nullable', "required_if:{$type},percent", 'numeric', 'decimal:0,4', 'gt:0', 'max:100'],
            "{$prefix}_amount_minor" => ['nullable', "required_if:{$type},fixed", 'integer', 'min:1', 'max:100000000000'],
        ];
    }

    /**
     * Validated values with the fields that do not match each penalty type cleared.
     *
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        $data = $this->validated();

        foreach (['penalty' => 'penalty_type', 'no_show' => 'no_show_type'] as $prefix => $typeField) {
            $type = PenaltyType::from((string) $data[$typeField]);
            $data["{$prefix}_nights"] = $type === PenaltyType::Nights ? (int) $data["{$prefix}_nights"] : null;
            $data["{$prefix}_percent"] = $type === PenaltyType::Percent ? (string) $data["{$prefix}_percent"] : null;
            $data["{$prefix}_amount_minor"] = $type === PenaltyType::Fixed ? (int) $data["{$prefix}_amount_minor"] : null;
        }

        return $data;
    }
}
