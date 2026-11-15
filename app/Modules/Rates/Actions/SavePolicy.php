<?php

declare(strict_types=1);

namespace App\Modules\Rates\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;
use App\Modules\Rates\Models\CancellationPolicy;
use App\Modules\Rates\Models\DepositPolicy;

/**
 * Create or update cancellation and deposit policies (RTE-07). Existing reservations are not
 * affected: they keep the snapshot taken when they were booked (spec §6.3).
 */
final class SavePolicy
{
    /**
     * @param  array<string, mixed>  $input  normalised, validated input
     */
    public function cancellation(User $actor, Property $property, array $input, ?CancellationPolicy $policy = null): CancellationPolicy
    {
        $policy ??= new CancellationPolicy($this->owned($actor, $property));
        $policy->fill([...$input, 'updated_by' => $actor->id])->save();

        return $policy->loadCount('ratePlans');
    }

    /**
     * @param  array<string, mixed>  $input  normalised, validated input
     */
    public function deposit(User $actor, Property $property, array $input, ?DepositPolicy $policy = null): DepositPolicy
    {
        $policy ??= new DepositPolicy($this->owned($actor, $property));
        $policy->fill([...$input, 'updated_by' => $actor->id])->save();

        return $policy->loadCount('ratePlans');
    }

    /**
     * @return array<string, int>
     */
    private function owned(User $actor, Property $property): array
    {
        return ['organization_id' => $property->organization_id, 'property_id' => $property->id, 'created_by' => $actor->id];
    }
}
