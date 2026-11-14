<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Modules\Inventory\Enums\HousekeepingStatus;
use Spatie\LaravelData\Data;

class HousekeepingStatusData extends Data
{
    public function __construct(
        public HousekeepingStatus $value,
        public string $label,
    ) {}

    public static function fromEnum(HousekeepingStatus $status): self
    {
        return new self($status, $status->label());
    }
}
