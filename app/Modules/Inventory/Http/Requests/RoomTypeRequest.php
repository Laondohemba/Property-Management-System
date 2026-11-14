<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Enums\BedType;
use App\Modules\Inventory\Models\RoomType;
use App\Support\PropertyContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Room type setup (SYS-04): capacity, bed types and amenities. Authorised in the controller.
 */
class RoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $property = app(PropertyContext::class)->require();
        /** @var RoomType|null $roomType */
        $roomType = $this->route('roomType');
        $required = $roomType ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'code' => [
                $required, 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('room_types', 'code')->where('property_id', $property->id)->ignore($roomType?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_occupancy' => [$required, 'integer', 'min:1', 'max:20'],
            'max_adults' => [$required, 'integer', 'min:1', 'max:20'],
            'max_children' => [$required, 'integer', 'min:0', 'max:20'],
            'max_occupancy' => [$required, 'integer', 'min:1', 'max:30'],
            'bed_configuration' => [$required, 'array', 'min:1', 'max:10'],
            'bed_configuration.*.type' => ['required', Rule::enum(BedType::class)],
            'bed_configuration.*.count' => ['required', 'integer', 'min:1', 'max:10'],
            'amenity_ids' => ['sometimes', 'array', 'max:50'],
            'amenity_ids.*' => ['string', Rule::exists('amenities', 'public_id')->where('property_id', $property->id)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Capacity must be internally consistent: base ≤ max ≤ adults + children.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var RoomType|null $current */
                $current = $this->route('roomType');
                $value = fn (string $key): ?int => $this->has($key) ? (int) $this->input($key) : $current?->getAttribute($key);

                [$base, $max, $adults, $children] = [$value('base_occupancy'), $value('max_occupancy'), $value('max_adults'), $value('max_children')];

                if ($base === null || $max === null || $adults === null || $children === null) {
                    return;
                }

                if ($base > $max) {
                    $validator->errors()->add('base_occupancy', 'Base occupancy cannot exceed the maximum occupancy.');
                }

                if ($max > $adults + $children) {
                    $validator->errors()->add('max_occupancy', 'Maximum occupancy cannot exceed max adults plus max children.');
                }

                if ($adults > $max) {
                    $validator->errors()->add('max_adults', 'Max adults cannot exceed the maximum occupancy.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => 'Use letters, numbers, dashes or underscores only.'];
    }
}
