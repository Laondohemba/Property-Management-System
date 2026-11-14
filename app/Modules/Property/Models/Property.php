<?php

declare(strict_types=1);

namespace App\Modules\Property\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Property\Policies\PropertyPolicy;
use App\Support\Tenancy\BelongsToOrganization;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A hotel or other venue within an organisation (spec §2.7, SYS-03).
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
    use BelongsToOrganization, HasFactory, HasUlids, SoftDeletes;

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
}
