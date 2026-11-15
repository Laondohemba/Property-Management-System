<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Audit\Audit;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Room-type gallery (SYS-04): upload, describe, reorder and remove images.
 *
 * Files go to the media disk (S3 in deployed environments, ARC-08) under a random name, so
 * nothing about the uploader's file system leaks into storage paths. Every change is written
 * to the room type's audit history (AUD-01).
 */
final class ManageRoomTypeImages
{
    public function add(User $actor, Property $property, RoomType $roomType, UploadedFile $file, ?string $alt): Media
    {
        $count = $roomType->media()->where('collection_name', RoomType::IMAGES)->count();

        if ($count >= RoomType::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'image' => 'A room type can have at most '.RoomType::MAX_IMAGES.' images. Remove one first.',
            ]);
        }

        $originalName = Str::limit($file->getClientOriginalName(), 200, '');
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());

        $media = $roomType->addMedia($file)
            ->usingName(pathinfo($originalName, PATHINFO_FILENAME))
            ->usingFileName(Str::lower((string) Str::ulid()).'.'.$extension)
            ->withCustomProperties([
                'alt' => $alt,
                'original_name' => $originalName,
                'uploaded_by' => $actor->public_id,
            ])
            ->toMediaCollection(RoomType::IMAGES);

        $this->audit('image_added', "Room type {$roomType->code}: image {$originalName} added", $roomType, $media, $actor, $property);

        return $media;
    }

    public function describe(User $actor, Property $property, RoomType $roomType, Media $media, ?string $alt): Media
    {
        $before = $media->getCustomProperty('alt');
        $media->setCustomProperty('alt', $alt)->save();

        if ($before !== $alt) {
            $this->audit('image_updated', "Room type {$roomType->code}: image description changed", $roomType, $media, $actor, $property, [
                'old' => ['alt' => $before],
                'attributes' => ['alt' => $alt],
            ]);
        }

        return $media;
    }

    /**
     * @param  list<string>  $uuids  Every image of the room type, in the new order.
     */
    public function reorder(User $actor, Property $property, RoomType $roomType, array $uuids): void
    {
        /** @var array<string, int> $ids uuid => id */
        $ids = $roomType->media()
            ->where('collection_name', RoomType::IMAGES)
            ->pluck('id', 'uuid')
            ->all();

        $requested = $uuids;
        $known = array_keys($ids);
        sort($requested);
        sort($known);

        if ($requested !== $known) {
            throw ValidationException::withMessages([
                'ids' => 'Send every image of this room type exactly once.',
            ]);
        }

        DB::transaction(fn () => Media::setNewOrder(array_map(fn (string $uuid): int => $ids[$uuid], $uuids)));

        $this->audit('images_reordered', "Room type {$roomType->code}: images reordered", $roomType, null, $actor, $property, [
            'order' => $uuids,
        ]);
    }

    public function remove(User $actor, Property $property, RoomType $roomType, Media $media): void
    {
        $name = $media->getCustomProperty('original_name', $media->file_name);
        $uuid = $media->uuid;

        // Deletes the original and every conversion from the media disk.
        $media->delete();

        $this->audit('image_removed', "Room type {$roomType->code}: image {$name} removed", $roomType, null, $actor, $property, [
            'image' => $uuid,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(
        string $event,
        string $description,
        RoomType $roomType,
        ?Media $media,
        User $actor,
        Property $property,
        array $properties = [],
    ): void {
        if ($media !== null) {
            $properties += ['image' => $media->uuid, 'file' => $media->getCustomProperty('original_name'), 'size' => $media->size];
        }

        Audit::record(Audit::DATA, $event, $description, $roomType, $properties, $actor, $property);
    }
}
