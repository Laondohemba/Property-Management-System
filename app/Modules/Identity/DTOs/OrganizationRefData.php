<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use Spatie\LaravelData\Data;

class OrganizationRefData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
