<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Amenity;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveRoomType
{
    /**
     * Create (no $roomType) or update a room type and its amenities in one transaction.
     *
     * @param  array<string, mixed>  $input  validated input
     */
    public function handle(User $actor, Property $property, array $input, ?RoomType $roomType = null): RoomType
    {
        return DB::transaction(function () use ($actor, $property, $input, $roomType): RoomType {
            $attributes = Arr::except($input, ['amenity_ids']);

            if (isset($attributes['bed_configuration'])) {
                $attributes['bed_configuration'] = array_values(array_map(
                    fn (array $bed): array => ['type' => $bed['type'], 'count' => (int) $bed['count']],
                    $attributes['bed_configuration'],
                ));
            }

            $roomType ??= new RoomType([
                'property_id' => $property->id,
                'organization_id' => $property->organization_id,
                'created_by' => $actor->id,
            ]);
            $roomType->fill([...$attributes, 'updated_by' => $actor->id])->save();

            if (array_key_exists('amenity_ids', $input)) {
                $roomType->amenities()->sync(
                    Amenity::forProperty($property)->whereIn('public_id', $input['amenity_ids'])->pluck('id'),
                );
            }

            return $roomType->load(['amenities', 'media'])->loadCount('rooms');
        });
    }
}
