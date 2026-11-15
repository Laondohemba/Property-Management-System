<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exceptions;

use App\Support\Http\ProblemException;

/**
 * Not enough units on one or more nights (spec §6.3, §7.4). Rendered as 409 with the nights
 * that failed, so the screen can say exactly which dates are the problem.
 */
final class InventoryUnavailable extends ProblemException
{
    /**
     * @param  list<array{date: string, available: int}>  $nights
     */
    public static function forNights(string $detail, array $nights): self
    {
        return new self(409, 'inventory_unavailable', 'Not enough inventory', $detail, ['nights' => $nights]);
    }
}
