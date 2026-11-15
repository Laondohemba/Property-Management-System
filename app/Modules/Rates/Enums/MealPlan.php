<?php

declare(strict_types=1);

namespace App\Modules\Rates\Enums;

/**
 * Meals included in a rate plan (RTE-01).
 */
enum MealPlan: string
{
    case RoomOnly = 'room_only';
    case Breakfast = 'breakfast';
    case HalfBoard = 'half_board';
    case FullBoard = 'full_board';
    case AllInclusive = 'all_inclusive';

    public function label(): string
    {
        return match ($this) {
            self::RoomOnly => 'Room only',
            self::Breakfast => 'Bed and breakfast',
            self::HalfBoard => 'Half board',
            self::FullBoard => 'Full board',
            self::AllInclusive => 'All inclusive',
        };
    }
}
