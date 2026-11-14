<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Enums\StaffStatus;
use Spatie\LaravelData\Data;

/**
 * A staff member as shown in user administration (SYS-01).
 */
class StaffUserData extends Data
{
    /**
     * @param  array<int, StaffAssignmentData>  $assignments  only at properties the viewer manages
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public StaffStatus $status,
        public bool $is_active,
        public bool $is_super_admin,
        public bool $is_self,
        public bool $two_factor_enabled,
        public ?string $last_login_at,
        public ?string $invited_at,
        public array $assignments,
    ) {}
}
