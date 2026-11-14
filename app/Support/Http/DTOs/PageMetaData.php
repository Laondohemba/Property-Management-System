<?php

declare(strict_types=1);

namespace App\Support\Http\DTOs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;

/**
 * Pagination block of list responses (spec §8.1). List-specific meta classes extend it.
 */
class PageMetaData extends Data
{
    public function __construct(
        public int $page,
        public int $per_page,
        public int $total,
        public int $last_page,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self($paginator->currentPage(), $paginator->perPage(), $paginator->total(), $paginator->lastPage());
    }
}
