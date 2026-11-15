<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use Spatie\LaravelData\Data;

/** A policy chosen on a rate plan, with the wording guests see. */
class PolicyRefData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $summary,
    ) {}
}
