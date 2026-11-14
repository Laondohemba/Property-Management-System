<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Modules\Inventory\Enums\BedType;
use Spatie\LaravelData\Data;

class BedData extends Data
{
    public function __construct(
        public BedType $type,
        public string $label,
        public int $count,
    ) {}
}
