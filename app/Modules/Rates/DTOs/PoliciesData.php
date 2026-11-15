<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use Spatie\LaravelData\Data;

/** Policies screen (GET /bff/settings/policies): both kinds at once. */
class PoliciesData extends Data
{
    /**
     * @param  array<int, CancellationPolicyData>  $cancellation
     * @param  array<int, DepositPolicyData>  $deposit
     */
    public function __construct(
        public array $cancellation,
        public array $deposit,
    ) {}
}
