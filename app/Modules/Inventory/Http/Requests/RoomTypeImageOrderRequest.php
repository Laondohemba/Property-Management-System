<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\RoomType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** New gallery order: every image id of the room type, first one is the cover. */
class RoomTypeImageOrderRequest extends FormRequest
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
            'ids' => ['required', 'array', 'max:'.RoomType::MAX_IMAGES],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        /** @var list<string> $ids */
        $ids = array_values($this->validated('ids'));

        return $ids;
    }
}
