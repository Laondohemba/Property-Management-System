<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateRoomRange
{
    /**
     * Create one room per number, all or nothing.
     *
     * @param  list<string>  $numbers
     * @return Collection<int, Room>
     */
    public function handle(User $actor, Property $property, string $roomTypePublicId, array $numbers, ?string $floor): Collection
    {
        $roomType = RoomType::forProperty($property)->where('public_id', $roomTypePublicId)->firstOrFail();
        $nextSortOrder = (int) Room::forProperty($property)->max('sort_order') + 1;

        return DB::transaction(fn (): Collection => collect($numbers)->values()->map(
            fn (string $number, int $index): Room => Room::create([
                'organization_id' => $property->organization_id,
                'property_id' => $property->id,
                'room_type_id' => $roomType->id,
                'number' => $number,
                'floor' => $floor,
                'sort_order' => $nextSortOrder + $index,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->setRelation('roomType', $roomType),
        ));
    }
}
