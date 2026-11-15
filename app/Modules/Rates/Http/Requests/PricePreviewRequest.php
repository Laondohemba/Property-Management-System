<?php

declare(strict_types=1);

namespace App\Modules\Rates\Http\Requests;

use App\Modules\Inventory\Models\RoomType;
use App\Support\PropertyContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** ?room_type_id=&adults=&children=&nights= */
class PricePreviewRequest extends FormRequest
{
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
            'room_type_id' => ['required', 'string', Rule::exists('room_types', 'public_id')
                ->where('property_id', app(PropertyContext::class)->require()->id)->whereNull('deleted_at')],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'nights' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function roomType(): RoomType
    {
        return RoomType::forProperty(app(PropertyContext::class)->require())
            ->where('public_id', (string) $this->validated('room_type_id'))
            ->firstOrFail();
    }
}
