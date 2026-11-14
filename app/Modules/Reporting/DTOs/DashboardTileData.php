<?php

declare(strict_types=1);

namespace App\Modules\Reporting\DTOs;

use Spatie\LaravelData\Data;

class DashboardTileData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public int $value,
    ) {}
}
