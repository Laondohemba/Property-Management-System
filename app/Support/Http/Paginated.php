<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * Pagination conventions for BFF lists (spec §8.1): page/per_page, default 25, max 100.
 */
final class Paginated
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    public static function perPage(Request $request): int
    {
        return max(1, min(self::MAX_PER_PAGE, $request->integer('per_page', self::DEFAULT_PER_PAGE)));
    }
}
