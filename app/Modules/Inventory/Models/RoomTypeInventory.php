<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToProperty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Master inventory: one row per room type per date (spec §7.3, CHN-01).
 *
 * Only InventoryLedger changes these rows, inside a transaction with the rows locked in
 * ascending date order (spec §6.3). Never write to them from anywhere else.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property int $room_type_id
 * @property Carbon $date
 * @property int $total
 * @property int $blocked
 * @property int $sold
 * @property int $overbook_allowance
 * @property int $version
 * @property-read RoomType $roomType
 */
#[Table('room_type_inventory')]
#[Fillable(['organization_id', 'property_id', 'room_type_id', 'date', 'total', 'blocked', 'sold', 'overbook_allowance'])]
class RoomTypeInventory extends Model
{
    use BelongsToProperty;

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /** Units neither sold nor blocked; negative when overbooked. */
    public function available(): int
    {
        return $this->total - $this->blocked - $this->sold;
    }

    /** Units that may still be sold, including the overbooking allowance. */
    public function sellable(): int
    {
        return $this->available() + $this->overbook_allowance;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'total' => 'integer',
            'blocked' => 'integer',
            'sold' => 'integer',
            'overbook_allowance' => 'integer',
            'version' => 'integer',
        ];
    }
}
