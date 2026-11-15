<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveRoom
{
    /**
     * Create (no $room) or update a room.
     *
     * @param  array<string, mixed>  $input  validated input; room_type_id is a public id
     */
    public function handle(User $actor, Property $property, array $input, ?Room $room = null): Room
    {
        $attributes = Arr::except($input, ['room_type_id']);

        if (isset($input['room_type_id'])) {
            $attributes['room_type_id'] = RoomType::forProperty($property)->where('public_id', $input['room_type_id'])->value('id');
        }

        $room ??= new Room([
            'property_id' => $property->id,
            'organization_id' => $property->organization_id,
            'created_by' => $actor->id,
        ]);
        // One transaction with the inventory update the save triggers (Room::booted), so a change
        // refused for lack of inventory leaves the room as it was.
        DB::transaction(fn () => $room->fill([...$attributes, 'updated_by' => $actor->id])->save());

        return $room->load('roomType');
    }
}
