<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Modules\Inventory\Enums\BlockType;
use Spatie\LaravelData\Data;

class BlockTypeOptionData extends Data
{
    public function __construct(
        public BlockType $value,
        public string $label,
    ) {}

    /**
     * @return array<int, self>
     */
    public static function options(): array
    {
        return array_map(fn (BlockType $type): self => new self($type, $type->label()), BlockType::cases());
    }
}
