<?php

declare(strict_types=1);

namespace App\Modules\Rates\Models;

use App\Modules\Inventory\Models\RoomType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A room type sold under a rate plan, with its nightly base price and supplements (RTE-01).
 * The base price covers the room type's base occupancy; each guest above it pays a supplement.
 *
 * @property int $id
 * @property int $rate_plan_id
 * @property int $room_type_id
 * @property int $base_price_minor
 * @property int $extra_adult_minor
 * @property int $extra_child_minor
 * @property-read RatePlan $ratePlan
 * @property-read RoomType $roomType
 */
#[Fillable(['rate_plan_id', 'room_type_id', 'base_price_minor', 'extra_adult_minor', 'extra_child_minor'])]
class RatePlanRoomType extends Model
{
    /**
     * @return BelongsTo<RatePlan, $this>
     */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price_minor' => 'integer',
            'extra_adult_minor' => 'integer',
            'extra_child_minor' => 'integer',
        ];
    }
}
