<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Support\Http\DTOs\PageMetaData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Meta of GET /bff/users: pagination plus the screen's options.
 */
class StaffListMetaData extends PageMetaData
{
    /**
     * @param  array<int, PropertyRefData>  $properties
     * @param  array<int, AssignableRoleData>  $roles
     */
    public function __construct(
        int $page,
        int $per_page,
        int $total,
        int $last_page,
        public array $properties,
        public array $roles,
        public bool $can_manage_accounts,
    ) {
        parent::__construct($page, $per_page, $total, $last_page);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function make(LengthAwarePaginator $paginator, StaffOptionsData $options): self
    {
        return new self(
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->total(),
            $paginator->lastPage(),
            $options->properties,
            $options->roles,
            $options->can_manage_accounts,
        );
    }
}
