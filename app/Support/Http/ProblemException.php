<?php

declare(strict_types=1);

namespace App\Support\Http;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A domain failure that renders itself as RFC 9457 problem+json (spec §8.4) with a stable
 * machine-readable code, for example a 409 when inventory has run out.
 */
class ProblemException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extensions  extra members, e.g. the dates that failed
     */
    public function __construct(
        public readonly int $status,
        public readonly string $problemCode,
        public readonly string $title,
        ?string $detail = null,
        public readonly array $extensions = [],
    ) {
        parent::__construct($detail ?? $title);
    }

    public function render(): Response
    {
        return Problem::response($this->status, $this->problemCode, $this->title, $this->getMessage(), $this->extensions);
    }
}
