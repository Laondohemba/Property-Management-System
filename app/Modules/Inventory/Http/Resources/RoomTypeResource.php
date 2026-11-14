<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Enums\BedType;
use App\Modules\Inventory\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoomType
 */
class RoomTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'base_occupancy' => $this->base_occupancy,
            'max_adults' => $this->max_adults,
            'max_children' => $this->max_children,
            'max_occupancy' => $this->max_occupancy,
            'bed_configuration' => array_map(fn (array $bed): array => [
                'type' => $bed['type'],
                'label' => BedType::tryFrom($bed['type'])?->label() ?? $bed['type'],
                'count' => (int) $bed['count'],
            ], $this->bed_configuration ?? []),
            'amenities' => AmenityResource::collection($this->whenLoaded('amenities')),
            'rooms_count' => $this->whenCounted('rooms'),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
