<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\TaxCalculation;
use App\Modules\Billing\Models\Tax;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;

final class SaveTax
{
    /**
     * Create (no $tax) or update a tax. A percentage keeps only its rate; a fixed fee keeps only
     * its amount and is never inclusive.
     *
     * @param  array<string, mixed>  $input  validated input
     */
    public function handle(User $actor, Property $property, array $input, ?Tax $tax = null): Tax
    {
        $calculation = TaxCalculation::from((string) $input['calculation']);
        $percentage = $calculation === TaxCalculation::Percentage;

        $tax ??= new Tax([
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'created_by' => $actor->id,
        ]);

        $tax->fill([
            ...$input,
            'rate' => $percentage ? (string) $input['rate'] : null,
            'amount_minor' => $percentage ? null : (int) $input['amount_minor'],
            'is_inclusive' => $percentage && (bool) ($input['is_inclusive'] ?? false),
            'updated_by' => $actor->id,
        ])->save();

        return $tax;
    }
}
