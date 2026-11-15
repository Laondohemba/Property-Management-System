<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Support\Database\HasRecordStamps;
use App\Support\Tenancy\BelongsToProperty;
use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A sellable category of rooms at a property (SYS-04).
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $property_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int $base_occupancy
 * @property int $max_adults
 * @property int $max_children
 * @property int $max_occupancy
 * @property array<int, array{type: string, count: int}> $bed_configuration
 * @property int $sort_order
 * @property bool $is_active
 * @property-read Collection<int, Amenity> $amenities
 * @property-read Collection<int, Media> $media
 */
#[Fillable([
    'organization_id', 'property_id', 'name', 'code', 'description', 'base_occupancy', 'max_adults',
    'max_children', 'max_occupancy', 'bed_configuration', 'sort_order', 'is_active', 'created_by', 'updated_by',
])]
#[UseFactory(RoomTypeFactory::class)]
class RoomType extends Model implements HasMedia
{
    /** @use HasFactory<RoomTypeFactory> */
    use BelongsToProperty, HasFactory, HasRecordStamps, HasUlids, InteractsWithMedia, LogsActivity, SoftDeletes;

    /** Gallery shown in setup and, later, on booking channels (SYS-04). The first image is the cover. */
    public const IMAGES = 'images';

    public const MAX_IMAGES = 20;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'max_children' => 0,
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
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return BelongsToMany<Amenity, $this>
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class)->orderBy('name');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGES)
            ->useDisk((string) config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Only conversions are ever served: re-encoding drops EXIF metadata (camera, GPS) from the
     * original upload, which stays private on the media disk.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Small enough to build during the upload request, so the gallery updates at once.
        // Conversion settings first; the image manipulations after them are forwarded to the driver.
        $this->addMediaConversion('thumb')
            ->performOnCollections(self::IMAGES)
            ->nonOptimized()
            ->nonQueued()
            ->fit(Fit::Crop, 480, 320)
            ->format('webp')
            ->quality(80);

        // Full-size view: built on the worker pods.
        $this->addMediaConversion('large')
            ->performOnCollections(self::IMAGES)
            ->nonOptimized()
            ->queued()
            ->fit(Fit::Max, 1600, 1600)
            ->format('webp')
            ->quality(82);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bed_configuration' => 'array',
            'base_occupancy' => 'integer',
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'max_occupancy' => 'integer',
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
            ->logOnly(['name', 'code', 'description', 'base_occupancy', 'max_adults', 'max_children', 'max_occupancy', 'bed_configuration', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => "Room type {$this->code} {$event}");
    }
}
