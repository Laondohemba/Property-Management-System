<?php

declare(strict_types=1);

namespace App\Modules\Rates\Models;

use App\Modules\Rates\Enums\PenaltyType;
use App\Support\Database\HasRecordStamps;
use App\Support\Tenancy\BelongsToProperty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * When a booking can be cancelled for free, and what a late cancellation or a no-show costs
 * (RTE-07, RES-03, RES-04). Reservations keep a snapshot of the policy taken at booking time,
 * so changing a policy never changes existing bookings (spec §6.3).
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $property_id
 * @property string $name
 * @property int|null $free_cancellation_hours null: no free window (non-refundable)
 * @property PenaltyType $penalty_type
 * @property int|null $penalty_nights
 * @property string|null $penalty_percent
 * @property int|null $penalty_amount_minor
 * @property PenaltyType $no_show_type
 * @property int|null $no_show_nights
 * @property string|null $no_show_percent
 * @property int|null $no_show_amount_minor
 * @property string|null $guest_text custom wording shown to guests instead of the generated summary
 * @property bool $is_active
 */
#[Fillable([
    'organization_id', 'property_id', 'name', 'free_cancellation_hours',
    'penalty_type', 'penalty_nights', 'penalty_percent', 'penalty_amount_minor',
    'no_show_type', 'no_show_nights', 'no_show_percent', 'no_show_amount_minor',
    'guest_text', 'is_active', 'created_by', 'updated_by',
])]
class CancellationPolicy extends Model
{
    use BelongsToProperty, HasRecordStamps, HasUlids, LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
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
     * @return HasMany<RatePlan, $this>
     */
    public function ratePlans(): HasMany
    {
        return $this->hasMany(RatePlan::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'free_cancellation_hours' => 'integer',
            'penalty_type' => PenaltyType::class,
            'penalty_nights' => 'integer',
            'penalty_percent' => 'decimal:4',
            'penalty_amount_minor' => 'integer',
            'no_show_type' => PenaltyType::class,
            'no_show_nights' => 'integer',
            'no_show_percent' => 'decimal:4',
            'no_show_amount_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('data')
            ->logOnly([
                'name', 'free_cancellation_hours', 'penalty_type', 'penalty_nights', 'penalty_percent', 'penalty_amount_minor',
                'no_show_type', 'no_show_nights', 'no_show_percent', 'no_show_amount_minor', 'guest_text', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => "Cancellation policy {$this->name} {$event}");
    }
}
