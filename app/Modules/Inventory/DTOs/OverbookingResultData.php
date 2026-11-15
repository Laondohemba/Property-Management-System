<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use Spatie\LaravelData\Data;

class OverbookingResultData extends Data
{
    public function __construct(
        public int $nights_changed,
    ) {}
}
