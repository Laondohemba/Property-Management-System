<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** ?from=YYYY-MM-DD&to=YYYY-MM-DD, at most 92 nights. */
class InventoryGridRequest extends FormRequest
{
    public const MAX_NIGHTS = 92;

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
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', function (string $attribute, mixed $value, \Closure $fail): void {
                $from = $this->input('from');
                if (is_string($from) && is_string($value) && strtotime($from) !== false
                    && CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($value)) >= self::MAX_NIGHTS) {
                    $fail('Show at most '.self::MAX_NIGHTS.' nights at a time.');
                }
            }],
        ];
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('from'));
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('to'));
    }
}
