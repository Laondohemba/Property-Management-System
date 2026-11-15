<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One image of a room type's gallery (SYS-04). URLs are signed and expire
 * (config media-library.temporary_url_default_lifetime); the original upload is never exposed.
 */
class RoomTypeImageData extends Data
{
    public function __construct(
        public string $id,
        public string $file_name,
        public ?string $alt,
        public int $size,
        public int $position,
        public string $thumb_url,
        /** Null until the worker has built the full-size version; show the thumbnail meanwhile. */
        public ?string $large_url,
    ) {}

    public static function fromMedia(Media $media): self
    {
        $expires = now()->addMinutes((int) config('media-library.temporary_url_default_lifetime'));
        $alt = $media->getCustomProperty('alt');
        $originalName = $media->getCustomProperty('original_name');

        return new self(
            id: (string) $media->uuid,
            // Stored under a random name; show the name the file was uploaded with.
            file_name: is_string($originalName) ? $originalName : $media->file_name,
            alt: is_string($alt) && $alt !== '' ? $alt : null,
            size: (int) $media->size,
            position: (int) $media->order_column,
            thumb_url: $media->getTemporaryUrl($expires, 'thumb'),
            large_url: $media->hasGeneratedConversion('large') ? $media->getTemporaryUrl($expires, 'large') : null,
        );
    }
}
