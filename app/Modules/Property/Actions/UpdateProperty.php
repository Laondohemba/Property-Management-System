<?php

declare(strict_types=1);

namespace App\Modules\Property\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;

class UpdateProperty
{
    /**
     * The business date is deliberately not editable here: it only advances at night audit (spec §2.8).
     *
     * @param  array<string, mixed>  $attributes  validated input
     */
    public function handle(User $actor, Property $property, array $attributes): Property
    {
        $property->fill([...$attributes, 'updated_by' => $actor->id])->save();

        return $property;
    }
}
