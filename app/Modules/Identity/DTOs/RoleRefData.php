<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Models\Role;
use Spatie\LaravelData\Data;

/**
 * A role as referenced elsewhere (session, assignments): machine name and display label.
 */
class RoleRefData extends Data
{
    public function __construct(
        public string $name,
        public string $label,
    ) {}

    public static function fromRole(Role $role): self
    {
        return new self($role->name, $role->displayLabel());
    }
}
