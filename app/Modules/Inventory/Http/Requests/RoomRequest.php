<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\Room;
use App\Support\PropertyContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Room setup (SYS-04). Authorised in the controller.
 */
class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('number'))) {
            $this->merge(['number' => strtoupper(trim($this->input('number')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $property = app(PropertyContext::class)->require();
        /** @var Room|null $room */
        $room = $this->route('room');
        $required = $room ? 'sometimes' : 'required';

        return [
            'room_type_id' => [$required, 'string', Rule::exists('room_types', 'public_id')->where('property_id', $property->id)->whereNull('deleted_at')],
            'number' => [
                $required, 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9-]*$/',
                Rule::unique('rooms', 'number')->where('property_id', $property->id)->ignore($room?->id),
            ],
            'floor' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'number.regex' => 'Use letters, numbers or dashes only.',
            'number.unique' => 'This room number is already in use at this property.',
        ];
    }
}
