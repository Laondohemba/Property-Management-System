<?php

declare(strict_types=1);

namespace App\Modules\Property\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Property\Policies\PropertyPolicy;
use App\Support\Database\HasRecordStamps;
use App\Support\Tenancy\BelongsToOrganization;
use Carbon\Carbon;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A hotel or other venue within an organisation (spec §2.7, SYS-03).
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property string $name
 * @property string $code
 * @property string $timezone
 * @property string $currency
 * @property Carbon|null $business_date
 * @property string $check_in_time
 * @property string $check_out_time
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property array<string, mixed>|null $settings
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'name', 'code', 'timezone', 'currency', 'business_date',
    'check_in_time', 'check_out_time', 'email', 'phone', 'address_line1', 'address_line2',
    'city', 'state', 'postal_code', 'country', 'settings', 'is_active', 'created_by', 'updated_by',
])]
#[UseFactory(PropertyFactory::class)]
#[UsePolicy(PropertyPolicy::class)]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use BelongsToOrganization, HasFactory, HasRecordStamps, HasUlids, LogsActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
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
     * Staff assigned to this property, with their role here.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role_id')->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d',
            'settings' => 'array',
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
            ->logOnly(['name', 'code', 'timezone', 'currency', 'business_date', 'check_in_time', 'check_out_time', 'email', 'phone', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country', 'settings', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => "Property {$this->name} {$event}");
    }
}
