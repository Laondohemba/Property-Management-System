<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * One room-type image upload (multipart). The content is checked, not just the extension:
 * the MIME type is sniffed from the bytes and the file must decode as an image (spec §13).
 */
class RoomTypeImageRequest extends FormRequest
{
    public const MAX_KILOBYTES = 5 * 1024;

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
            'image' => [
                'required',
                File::image()
                    ->types(['jpeg', 'jpg', 'png', 'webp'])
                    ->max(self::MAX_KILOBYTES)
                    ->dimensions(Rule::dimensions()->minWidth(320)->minHeight(240)->maxWidth(10000)->maxHeight(10000)),
            ],
            'alt' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.max' => 'The image may not be larger than 5 MB.',
            'image.dimensions' => 'The image must be at least 320 × 240 pixels and at most 10,000 pixels on each side.',
        ];
    }
}
