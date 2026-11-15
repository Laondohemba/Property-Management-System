<?php

declare(strict_types=1);

namespace App\Modules\Rates\Models;

use App\Modules\Rates\Enums\BalanceDue;
use App\Modules\Rates\Enums\DepositDue;
use App\Modules\Rates\Enums\DepositType;
use App\Support\Database\HasRecordStamps;
use App\Support\Tenancy\BelongsToProperty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Deposit and payment terms of a rate plan (RTE-07): how much is taken up front and when,
 * whether a card guarantee is needed, and when the balance is paid.
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $property_id
 * @property string $name
 * @property DepositType $deposit_type
 * @property string|null $deposit_percent
 * @property int|null $deposit_nights
 * @property int|null $deposit_amount_minor
 * @property DepositDue $deposit_due
 * @property int|null $due_days_before_arrival
 * @property bool $requires_card_guarantee
 * @property BalanceDue $balance_due
 * @property string|null $guest_text
 * @property bool $is_active
 */
#[Fillable([
    'organization_id', 'property_id', 'name', 'deposit_type', 'deposit_percent', 'deposit_nights', 'deposit_amount_minor',
    'deposit_due', 'due_days_before_arrival', 'requires_card_guarantee', 'balance_due', 'guest_text', 'is_active',
    'created_by', 'updated_by',
])]
class DepositPolicy extends Model
{
    use BelongsToProperty, HasRecordStamps, HasUlids, LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'requires_card_guarantee' => false,
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
            'deposit_type' => DepositType::class,
            'deposit_percent' => 'decimal:4',
            'deposit_nights' => 'integer',
            'deposit_amount_minor' => 'integer',
            'deposit_due' => DepositDue::class,
            'due_days_before_arrival' => 'integer',
            'requires_card_guarantee' => 'boolean',
            'balance_due' => BalanceDue::class,
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('data')
            ->logOnly([
                'name', 'deposit_type', 'deposit_percent', 'deposit_nights', 'deposit_amount_minor', 'deposit_due',
                'due_days_before_arrival', 'requires_card_guarantee', 'balance_due', 'guest_text', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => "Deposit policy {$this->name} {$event}");
    }
}
