<?php

declare(strict_types=1);

namespace App\Modules\Rates\Models;

use App\Modules\Billing\Models\Tax;
use App\Modules\Rates\Enums\MealPlan;
use App\Support\Database\HasRecordStamps;
use App\Support\Tenancy\BelongsToProperty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A way of selling rooms (RTE-01): BAR, non-refundable, breakfast included, corporate, package.
 * Each has a base price per room type with occupancy supplements, a meal plan, policies
 * (RTE-07) and the taxes and fees that apply to it (spec §2.8).
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $property_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property MealPlan $meal_plan
 * @property int|null $cancellation_policy_id
 * @property int|null $deposit_policy_id
 * @property int|null $min_price_minor
 * @property int|null $max_price_minor
 * @property int $sort_order
 * @property bool $is_active
 * @property-read CancellationPolicy|null $cancellationPolicy
 * @property-read DepositPolicy|null $depositPolicy
 * @property-read Collection<int, RatePlanRoomType> $prices
 * @property-read Collection<int, Tax> $taxes
 */
#[Fillable([
    'organization_id', 'property_id', 'name', 'code', 'description', 'meal_plan', 'cancellation_policy_id',
    'deposit_policy_id', 'min_price_minor', 'max_price_minor', 'sort_order', 'is_active', 'created_by', 'updated_by',
])]
class RatePlan extends Model
{
    use BelongsToProperty, HasRecordStamps, HasUlids, LogsActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'meal_plan' => 'room_only',
        'is_active' => true,
        'sort_order' => 0,
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
     * @return BelongsTo<CancellationPolicy, $this>
     */
    public function cancellationPolicy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class);
    }

    /**
     * @return BelongsTo<DepositPolicy, $this>
     */
    public function depositPolicy(): BelongsTo
    {
        return $this->belongsTo(DepositPolicy::class);
    }

    /**
     * Base price and supplements per room type sold under this plan.
     *
     * @return HasMany<RatePlanRoomType, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(RatePlanRoomType::class);
    }

    /**
     * @return BelongsToMany<Tax, $this>
     */
    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'rate_plan_tax')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meal_plan' => MealPlan::class,
            'min_price_minor' => 'integer',
            'max_price_minor' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Audit trail (RTE-09); price changes per room type are recorded by SaveRatePlan.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('data')
            ->logOnly(['name', 'code', 'description', 'meal_plan', 'cancellation_policy_id', 'deposit_policy_id', 'min_price_minor', 'max_price_minor', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => "Rate plan {$this->code} {$event}");
    }
}
