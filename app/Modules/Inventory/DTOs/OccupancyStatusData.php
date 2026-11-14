<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Modules\Inventory\Enums\OccupancyStatus;
use Spatie\LaravelData\Data;

class OccupancyStatusData extends Data
{
    public function __construct(
        public OccupancyStatus $value,
        public string $label,
    ) {}

    public static function fromEnum(OccupancyStatus $status): self
    {
        return new self($status, $status->label());
    }
}
