<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Room
 */
class RoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'number' => $this->number,
            'floor' => $this->floor,
            'notes' => $this->notes,
            'room_type' => $this->whenLoaded('roomType', fn (): array => [
                'id' => $this->roomType->public_id,
                'name' => $this->roomType->name,
                'code' => $this->roomType->code,
            ]),
            'housekeeping_status' => ['value' => $this->housekeeping_status->value, 'label' => $this->housekeeping_status->label()],
            'occupancy_status' => ['value' => $this->occupancy_status->value, 'label' => $this->occupancy_status->label()],
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
