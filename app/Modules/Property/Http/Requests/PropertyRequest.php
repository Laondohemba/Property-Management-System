<?php

declare(strict_types=1);

namespace App\Modules\Property\Http\Requests;

use App\Modules\Property\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating and updating a property (SYS-03).
 * Authorisation is done by the controller through PropertyPolicy.
 */
class PropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'code' => is_string($this->input('code')) ? strtoupper(trim($this->input('code'))) : null,
            'currency' => is_string($this->input('currency')) ? strtoupper(trim($this->input('currency'))) : null,
            'country' => is_string($this->input('country')) ? strtoupper(trim($this->input('country'))) : null,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Property|null $property */
        $property = $this->route('property');
        $updating = $property !== null;
        $required = $updating ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'code' => [
                $required, 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('properties', 'code')
                    ->where('organization_id', $this->user()->organization_id)
                    ->whereNull('deleted_at')
                    ->ignore($property?->id),
            ],
            'timezone' => [$required, 'string', 'timezone:all'],
            'currency' => [$required, 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'check_in_time' => [$required, 'date_format:H:i'],
            'check_out_time' => [$required, 'date_format:H:i'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, dashes or underscores only.',
            'currency.regex' => 'Use a 3-letter ISO 4217 currency code, e.g. NGN.',
            'country.regex' => 'Use a 2-letter ISO 3166 country code, e.g. NG.',
        ];
    }
}
