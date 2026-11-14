<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use Spatie\LaravelData\Data;

class RoomTypeRefData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
    ) {}
}
