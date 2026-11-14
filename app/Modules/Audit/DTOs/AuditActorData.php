<?php

declare(strict_types=1);

namespace App\Modules\Audit\DTOs;

use Spatie\LaravelData\Data;

class AuditActorData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}
}
