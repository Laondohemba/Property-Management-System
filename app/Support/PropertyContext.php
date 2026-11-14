<?php

declare(strict_types=1);

namespace App\Support;

use App\Modules\Property\Models\Property;
use App\Support\Http\Problem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The active property for the current request, resolved from the X-Property-Id header
 * and validated against the user's assignments (spec §2.7). Registered as a scoped
 * binding, so it never survives beyond one request.
 */
final class PropertyContext
{
    private ?Property $property = null;

    public function set(?Property $property): void
    {
        $this->property = $property;
    }

    public function property(): ?Property
    {
        return $this->property;
    }

    /**
     * 404 unless the record belongs to the active property, so records of other properties
     * are indistinguishable from records that don't exist (spec §8.4).
     */
    public function ensureOwns(Model $model): void
    {
        if ((int) $model->getAttribute('property_id') !== $this->require()->id) {
            abort(404);
        }
    }

    public function require(): Property
    {
        return $this->property ?? throw new HttpResponseException(Problem::response(
            400,
            'property_required',
            'Property required',
            'Select a property (X-Property-Id header) to use this endpoint.',
        ));
    }
}
