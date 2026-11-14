<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\Amenity;
use App\Support\PropertyContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AmenityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Amenity|null $amenity */
        $amenity = $this->route('amenity');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('amenities', 'name')
                    ->where('property_id', app(PropertyContext::class)->require()->id)
                    ->ignore($amenity?->id),
            ],
        ];
    }
}
