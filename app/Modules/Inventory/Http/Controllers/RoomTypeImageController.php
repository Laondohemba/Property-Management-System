<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Inventory\Actions\ManageRoomTypeImages;
use App\Modules\Inventory\DTOs\RoomTypeImageData;
use App\Modules\Inventory\Http\Requests\RoomTypeImageOrderRequest;
use App\Modules\Inventory\Http\Requests\RoomTypeImageRequest;
use App\Modules\Inventory\Http\Requests\RoomTypeImageUpdateRequest;
use App\Modules\Inventory\Models\RoomType;
use App\Support\Http\Bff;
use App\Support\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * /bff/settings/room-types/{roomType}/images (SYS-04): the room type's gallery.
 */
class RoomTypeImageController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PropertyContext $context,
        private readonly ManageRoomTypeImages $images,
    ) {}

    public static function middleware(): array
    {
        return [new Middleware('can:'.Permission::SettingsRooms->value)];
    }

    public function store(RoomTypeImageRequest $request, RoomType $roomType): JsonResponse
    {
        $this->context->ensureOwns($roomType);

        /** @var UploadedFile $file */
        $file = $request->file('image');
        $alt = $request->validated('alt');

        $media = $this->images->add($this->actor($request), $this->context->require(), $roomType, $file, is_string($alt) ? $alt : null);

        return Bff::respond(RoomTypeImageData::fromMedia($media), status: 201);
    }

    public function update(RoomTypeImageUpdateRequest $request, RoomType $roomType, string $image): JsonResponse
    {
        $this->context->ensureOwns($roomType);
        $alt = $request->validated('alt');

        $media = $this->images->describe(
            $this->actor($request), $this->context->require(), $roomType, $this->find($roomType, $image), is_string($alt) ? $alt : null,
        );

        return Bff::respond(RoomTypeImageData::fromMedia($media));
    }

    /**
     * Returns the gallery in its new order.
     */
    public function reorder(RoomTypeImageOrderRequest $request, RoomType $roomType): JsonResponse
    {
        $this->context->ensureOwns($roomType);

        $this->images->reorder($this->actor($request), $this->context->require(), $roomType, $request->ids());

        return Bff::respond(
            $roomType->load('media')->getMedia(RoomType::IMAGES)
                ->map(fn (Media $media): RoomTypeImageData => RoomTypeImageData::fromMedia($media))
                ->values()->all(),
        );
    }

    public function destroy(Request $request, RoomType $roomType, string $image): Response
    {
        $this->context->ensureOwns($roomType);

        $this->images->remove($this->actor($request), $this->context->require(), $roomType, $this->find($roomType, $image));

        return response()->noContent();
    }

    /** An image of this room type only: another room type's id is simply not found. */
    private function find(RoomType $roomType, string $uuid): Media
    {
        return $roomType->media()
            ->where('collection_name', RoomType::IMAGES)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
