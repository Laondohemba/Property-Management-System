<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use Spatie\LaravelData\Data;

class TaxRefData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
    ) {}
}
