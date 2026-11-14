<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Modules\Inventory\Enums\BedType;
use Spatie\LaravelData\Data;

class BedTypeOptionData extends Data
{
    public function __construct(
        public BedType $value,
        public string $label,
    ) {}

    /**
     * @return array<int, self>
     */
    public static function options(): array
    {
        return array_map(fn (BedType $type): self => new self($type, $type->label()), BedType::cases());
    }
}
