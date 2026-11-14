<?php

declare(strict_types=1);

namespace App\Support\Http\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * RFC 9457 problem document returned for every BFF error (spec §8.4).
 * Built by App\Support\Http\Problem; this class is the typed contract.
 */
class ProblemData extends Data
{
    /**
     * @param  array<int, FieldErrorData>|Optional  $errors
     */
    public function __construct(
        public string $type,
        public string $title,
        public int $status,
        public string $code,
        public string|Optional $detail,
        public array|Optional $errors,
    ) {}
}
