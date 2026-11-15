<?php

declare(strict_types=1);

namespace App\Support\Http\DTOs;

use Spatie\LaravelData\Data;

/**
 * A choice for a select list: the stored value and its label.
 */
class OptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}

    /**
     * Options from a backed enum that has a label() method.
     *
     * @param  class-string<\BackedEnum>  $enum
     * @return array<int, self>
     */
    public static function fromEnum(string $enum): array
    {
        return array_map(
            fn (\BackedEnum $case): self => new self((string) $case->value, method_exists($case, 'label') ? (string) $case->label() : (string) $case->value),
            $enum::cases(),
        );
    }
}
