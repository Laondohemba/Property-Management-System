<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\ValidatesInventoryRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** How many units may be oversold on a range of nights, optionally only on some weekdays. */
class OverbookingRequest extends FormRequest
{
    use ValidatesInventoryRange;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->rangeRules('from', 'to'),
            'allowance' => ['required', 'integer', 'min:0', 'max:50'],
            'weekdays' => ['nullable', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->rangeMessages('from', 'to');
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('from'));
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('to'));
    }

    /**
     * @return list<int>|null ISO weekdays, null for every day
     */
    public function weekdays(): ?array
    {
        $weekdays = $this->validated('weekdays');

        return is_array($weekdays) ? array_values(array_map('intval', $weekdays)) : null;
    }
}
