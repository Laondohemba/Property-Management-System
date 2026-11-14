<?php

declare(strict_types=1);

namespace App\Support\Http\DTOs;

use Spatie\LaravelData\Data;

class FieldErrorData extends Data
{
    public function __construct(
        public string $field,
        public string $message,
    ) {}
}
