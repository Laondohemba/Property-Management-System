<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\HousekeepingStatus;
use App\Modules\Inventory\Enums\OccupancyStatus;
use App\Support\Tenancy\BelongsToProperty;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical room (SYS-04).
 */
#[Fillable([
    'organization_id', 'property_id', 'room_type_id', 'number', 'floor', 'notes',
    'sort_order', 'is_active', 'created_by', 'updated_by',
])]
#[UseFactory(RoomFactory::class)]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use BelongsToProperty, HasFactory, HasUlids, SoftDeletes;

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
}
