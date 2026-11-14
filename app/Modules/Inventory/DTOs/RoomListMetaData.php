<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Support\Http\DTOs\PageMetaData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoomListMetaData extends PageMetaData
{
    /**
     * @param  array<int, string>  $floors  floor filter options
     */
    public function __construct(
        int $page,
        int $per_page,
        int $total,
        int $last_page,
        public array $floors,
    ) {
        parent::__construct($page, $per_page, $total, $last_page);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  array<int, string>  $floors
     */
    public static function make(LengthAwarePaginator $paginator, array $floors): self
    {
        return new self($paginator->currentPage(), $paginator->perPage(), $paginator->total(), $paginator->lastPage(), $floors);
    }
}
