<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Enums\BlockType;
use App\Modules\Inventory\Http\Requests\Concerns\ValidatesInventoryRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Take units of a room type off sale for a range of nights (CAL-06). */
class InventoryBlockRequest extends FormRequest
{
    use ValidatesInventoryRange;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->rangeRules('from_date', 'to_date'),
            'type' => ['required', Rule::enum(BlockType::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'reason' => ['required', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->rangeMessages('from_date', 'to_date'),
            'expires_at.after' => 'The expiry must be in the future.',
        ];
    }

    public function type(): BlockType
    {
        return BlockType::from((string) $this->validated('type'));
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('from_date'));
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('to_date'));
    }

    public function expiresAt(): ?CarbonImmutable
    {
        $value = $this->validated('expires_at');

        return is_string($value) ? CarbonImmutable::parse($value)->utc() : null;
    }
}
