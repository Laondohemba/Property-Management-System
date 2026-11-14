<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Modules\Property\Models\Property;
use App\Support\PropertyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For records that belong to one property. Organisation scoping is automatic
 * (BelongsToOrganization); property scoping is explicit via forProperty() and
 * PropertyContext::ensureOwns(), as the spec puts it in policies, not global scopes (§2.7).
 *
 * @mixin Model
 */
trait BelongsToProperty
{
    use BelongsToOrganization;

    public static function bootBelongsToProperty(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('property_id') === null) {
                $model->setAttribute('property_id', app(PropertyContext::class)->property()?->id);
            }

            if ($model->getAttribute('organization_id') === null && $model->getAttribute('property_id') !== null) {
                $model->setAttribute(
                    'organization_id',
                    Property::withoutGlobalScopes()->whereKey($model->getAttribute('property_id'))->value('organization_id'),
                );
            }
        });
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForProperty(Builder $query, Property $property): void
    {
        $query->where($this->qualifyColumn('property_id'), $property->id);
    }
}
