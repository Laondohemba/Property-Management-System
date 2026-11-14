<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use Spatie\LaravelData\Data;

class StaffAssignmentData extends Data
{
    public function __construct(
        public PropertyRefData $property,
        public ?RoleRefData $role,
    ) {}
}
