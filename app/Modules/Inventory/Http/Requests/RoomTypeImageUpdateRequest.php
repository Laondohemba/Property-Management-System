<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Change an image's description (alternative text). */
class RoomTypeImageUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $alt = $this->input('alt');
        $this->merge(['alt' => is_string($alt) && trim($alt) !== '' ? trim($alt) : null]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'alt' => ['present', 'nullable', 'string', 'max:200'],
        ];
    }
}
