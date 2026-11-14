<?php

declare(strict_types=1);

namespace App\Modules\Property\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;

class CreateProperty
{
    /**
     * @param  array<string, mixed>  $attributes  validated input
     */
    public function handle(User $actor, array $attributes): Property
    {
        return Property::create([
            ...$attributes,
            'organization_id' => $actor->organization_id,
            // The first business date is "today" in the property's own timezone (spec §2.8).
            'business_date' => now($attributes['timezone'])->toDateString(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}
