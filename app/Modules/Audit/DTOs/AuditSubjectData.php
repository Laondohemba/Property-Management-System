<?php

declare(strict_types=1);

namespace App\Modules\Audit\DTOs;

use Spatie\LaravelData\Data;

class AuditSubjectData extends Data
{
    public function __construct(
        /** e.g. "Room", "User", "Role" */
        public string $type,
        /** Public id when the record still exists. */
        public ?string $id,
        public ?string $label,
    ) {}
}
