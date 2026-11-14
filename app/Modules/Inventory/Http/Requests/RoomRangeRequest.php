<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\Room;
use App\Support\PropertyContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create a run of numbered rooms at once, e.g. 101–120 on floor 1.
 */
class RoomRangeRequest extends FormRequest
{
    public const MAX_ROOMS = 200;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('prefix'))) {
            $this->merge(['prefix' => strtoupper(trim($this->input('prefix')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $property = app(PropertyContext::class)->require();

        return [
            'room_type_id' => ['required', 'string', Rule::exists('room_types', 'public_id')->where('property_id', $property->id)->whereNull('deleted_at')],
            'floor' => ['nullable', 'string', 'max:20'],
            'prefix' => ['nullable', 'string', 'max:5', 'regex:/^[A-Z0-9-]*$/'],
            'from' => ['required', 'integer', 'min:0', 'max:999999'],
            'to' => ['required', 'integer', 'gte:from', 'max:999999'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->integer('to') - $this->integer('from') + 1 > self::MAX_ROOMS) {
                    $validator->errors()->add('to', 'Create at most '.self::MAX_ROOMS.' rooms at a time.');

                    return;
                }

                $existing = Room::forProperty(app(PropertyContext::class)->require())
                    ->whereIn('number', $this->numbers())
                    ->orderByRaw('LENGTH(number), number')
                    ->limit(10)
                    ->pluck('number');

                if ($existing->isNotEmpty()) {
                    $validator->errors()->add('from', 'These room numbers already exist: '.$existing->implode(', ').'.');
                }
            },
        ];
    }

    /**
     * @return list<string>
     */
    public function numbers(): array
    {
        $prefix = (string) $this->input('prefix', '');

        return array_map(fn (int $number): string => $prefix.$number, range($this->integer('from'), $this->integer('to')));
    }
}
