<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\TaxCalculation;
use App\Support\Database\HasRecordStamps;
use App\Support\Tenancy\BelongsToProperty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A tax or fee of a property (spec §2.8): VAT, service charge, tourism or city levy. No rate is
 * hard-coded; each property defines its own, and each rate plan chooses which apply.
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $property_id
 * @property string $name
 * @property string $code
 * @property TaxCalculation $calculation
 * @property string|null $rate percentage as a decimal string, e.g. "7.5000"
 * @property int|null $amount_minor fixed amount in the property currency
 * @property bool $is_inclusive
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable([
    'organization_id', 'property_id', 'name', 'code', 'calculation', 'rate', 'amount_minor', 'is_inclusive',
    'sort_order', 'is_active', 'created_by', 'updated_by',
])]
class Tax extends Model
{
    use BelongsToProperty, HasRecordStamps, HasUlids, LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_inclusive' => false,
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calculation' => TaxCalculation::class,
            'rate' => 'decimal:4',
            'amount_minor' => 'integer',
            'is_inclusive' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('data')
            ->logOnly(['name', 'code', 'calculation', 'rate', 'amount_minor', 'is_inclusive', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => "Tax {$this->code} {$event}");
    }
}
