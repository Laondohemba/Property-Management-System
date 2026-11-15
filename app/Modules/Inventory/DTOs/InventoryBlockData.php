<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Modules\Inventory\Enums\BlockType;
use App\Modules\Inventory\Models\InventoryBlock;
use App\Support\Http\DTOs\ActorRefData;
use App\Support\Http\DTOs\RecordStampsData;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * A room-type block (CAL-06).
 */
class InventoryBlockData extends Data
{
    public function __construct(
        public string $id,
        public RoomTypeRefData $room_type,
        public BlockType $type,
        public string $type_label,
        public int $quantity,
        /** First night, YYYY-MM-DD. */
        public string $from_date,
        /** Last night (inclusive), YYYY-MM-DD. */
        public string $to_date,
        public string $reason,
        public ?string $expires_at,
        public ?string $released_at,
        public ?ActorRefData $released_by,
        /** active: holding units · ended: its last night has passed · released: given back. */
        public string $status,
        public RecordStampsData $stamps,
    ) {}

    /**
     * Expects roomType, releasedBy and the stamp relations loaded.
     */
    public static function fromModel(InventoryBlock $block, CarbonInterface $businessDate): self
    {
        $status = match (true) {
            $block->released_at !== null => 'released',
            $block->to_date->lt($businessDate->copy()->startOfDay()) => 'ended',
            default => 'active',
        };

        return new self(
            id: $block->public_id,
            room_type: new RoomTypeRefData($block->roomType->public_id, $block->roomType->name, $block->roomType->code),
            type: $block->type,
            type_label: $block->type->label(),
            quantity: $block->quantity,
            from_date: $block->from_date->toDateString(),
            to_date: $block->to_date->toDateString(),
            reason: $block->reason,
            expires_at: $block->expires_at?->toIso8601ZuluString(),
            released_at: $block->released_at?->toIso8601ZuluString(),
            released_by: ActorRefData::fromUser($block->releasedBy),
            status: $status,
            stamps: RecordStampsData::fromModel($block),
        );
    }
}
