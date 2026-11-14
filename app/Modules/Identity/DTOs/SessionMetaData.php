<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use Spatie\LaravelData\Data;

class SessionMetaData extends Data
{
    public function __construct(
        public string $app_version,
    ) {}
}
