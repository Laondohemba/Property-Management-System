<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\BlockType;
use App\Support\Database\HasRecordStamps;
use App\Support\Tenancy\BelongsToProperty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Units of a room type taken off sale for a range of nights (CAL-06): out of order, out of
 * service, owner use or a hold. While active, its quantity is counted in `blocked` on every
 * covered night of room_type_inventory. Released blocks are kept for the record.
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $property_id
 * @property int $room_type_id
 * @property BlockType $type
 * @property int $quantity
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property string $reason
 * @property Carbon|null $expires_at
 * @property Carbon|null $released_at
 * @property int|null $released_by
 * @property-read RoomType $roomType
 * @property-read User|null $releasedBy
 */
#[Fillable([
    'organization_id', 'property_id', 'room_type_id', 'type', 'quantity', 'from_date', 'to_date', 'reason',
    'expires_at', 'released_at', 'released_by', 'created_by', 'updated_by',
])]
class InventoryBlock extends Model
{
    use BelongsToProperty, HasRecordStamps, HasUlids, LogsActivity;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }

    /**
     * Not yet released.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('released_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'quantity' => 'integer',
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * Audit trail (spec §5.12, AUD-01).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('data')
            ->logOnly(['type', 'quantity', 'from_date', 'to_date', 'reason', 'expires_at', 'released_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => match ($event) {
                'created' => "Block of {$this->quantity} added",
                'updated' => $this->released_at !== null ? "Block of {$this->quantity} released" : 'Block updated',
                default => "Block {$event}",
            });
    }
}
