<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\HousekeepingStatus;
use App\Modules\Inventory\Enums\OccupancyStatus;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Support\Database\HasRecordStamps;
use App\Support\Tenancy\BelongsToProperty;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A physical room (SYS-04).
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $property_id
 * @property int $room_type_id
 * @property string $number
 * @property string|null $floor
 * @property string|null $notes
 * @property HousekeepingStatus $housekeeping_status
 * @property OccupancyStatus $occupancy_status
 * @property int $sort_order
 * @property bool $is_active
 * @property-read RoomType $roomType
 */
#[Fillable([
    'organization_id', 'property_id', 'room_type_id', 'number', 'floor', 'notes',
    'sort_order', 'is_active', 'created_by', 'updated_by',
])]
#[UseFactory(RoomFactory::class)]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use BelongsToProperty, HasFactory, HasRecordStamps, HasUlids, LogsActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'housekeeping_status' => 'clean',
        'occupancy_status' => 'vacant',
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * Inventory follows the active rooms (CHN-01). Runs inside the caller's transaction, so a
     * change that would leave fewer rooms than are sold or blocked is rolled back with it.
     */
    protected static function booted(): void
    {
        static::saved(function (Room $room): void {
            // wasRecentlyCreated stays true for the instance's lifetime, so "created by this save"
            // also needs an empty change set (an update on the same instance records changes).
            $createdNow = $room->wasRecentlyCreated && $room->getChanges() === [];

            if (! $createdNow && ! $room->wasChanged(['is_active', 'room_type_id'])) {
                return;
            }

            $ledger = app(InventoryLedger::class);

            if (! $createdNow && $room->wasChanged('room_type_id')) {
                // The type before this save (the original is already synced by now).
                $previous = $room->getPrevious()['room_type_id'] ?? null;
                if ($previous !== null) {
                    $ledger->syncTotal(RoomType::withTrashed()->withoutGlobalScopes()->findOrFail((int) $previous));
                }
            }

            $ledger->syncTotal(RoomType::withTrashed()->withoutGlobalScopes()->findOrFail($room->room_type_id));
        });

        $sync = fn (Room $room) => app(InventoryLedger::class)
            ->syncTotal(RoomType::withTrashed()->withoutGlobalScopes()->findOrFail($room->room_type_id));

        static::deleted($sync);
        static::restored($sync);
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
        return $this->belongsTo(RoomType::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'housekeeping_status' => HousekeepingStatus::class,
            'occupancy_status' => OccupancyStatus::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Audit trail (spec §5.12, AUD-01): before/after values of the listed attributes only.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('data')
            ->logOnly(['number', 'room_type_id', 'floor', 'notes', 'housekeeping_status', 'occupancy_status', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => "Room {$this->number} {$event}");
    }
}
