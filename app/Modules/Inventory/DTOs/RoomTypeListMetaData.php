<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Support\Http\DTOs\PageMetaData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoomTypeListMetaData extends PageMetaData
{
    /**
     * @param  array<int, BedTypeOptionData>  $bed_types
     */
    public function __construct(
        int $page,
        int $per_page,
        int $total,
        int $last_page,
        public array $bed_types,
    ) {
        parent::__construct($page, $per_page, $total, $last_page);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function make(LengthAwarePaginator $paginator): self
    {
        return new self($paginator->currentPage(), $paginator->perPage(), $paginator->total(), $paginator->lastPage(), BedTypeOptionData::options());
    }
}
