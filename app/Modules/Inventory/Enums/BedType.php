<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum BedType: string
{
    case Single = 'single';
    case Twin = 'twin';
    case Double = 'double';
    case Queen = 'queen';
    case King = 'king';
    case SofaBed = 'sofa_bed';
    case Bunk = 'bunk';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Twin => 'Twin',
            self::Double => 'Double',
            self::Queen => 'Queen',
            self::King => 'King',
            self::SofaBed => 'Sofa bed',
            self::Bunk => 'Bunk',
        };
    }
}
