<?php

declare(strict_types=1);

namespace App\Modules\Property\Models;

use App\Modules\Identity\Models\User;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Tenant root (spec §2.7). Every business table carries organization_id.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['name', 'slug', 'is_active', 'settings'])]
#[UseFactory(OrganizationFactory::class)]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    /**
     * Mirror the database defaults so freshly created instances behave the same.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Only the public identifier is a ULID; the primary key stays an internal auto-increment.
     *
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
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Role names whose holders must use two-factor authentication (SYS-01).
     *
     * @return list<string>
     */
    public function twoFactorRoles(): array
    {
        return array_values($this->settings['two_factor_roles'] ?? []);
    }

    public function setTwoFactorRequired(string $roleName, bool $required): void
    {
        $roles = collect($this->twoFactorRoles())
            ->reject(fn (string $name): bool => $name === $roleName)
            ->when($required, fn ($roles) => $roles->push($roleName))
            ->values()
            ->all();

        $this->settings = [...($this->settings ?? []), 'two_factor_roles' => $roles];
        $this->save();
    }

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Audit trail (spec §5.12, AUD-01): before/after values of the listed attributes only.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('data')
            ->logOnly(['name', 'slug', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => "Organisation {$this->name} {$event}");
    }
}
