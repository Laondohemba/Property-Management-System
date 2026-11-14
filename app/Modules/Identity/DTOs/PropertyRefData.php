<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Property\Models\Property;
use Spatie\LaravelData\Data;

class PropertyRefData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
    ) {}

    public static function fromProperty(Property $property): self
    {
        return new self($property->public_id, $property->name, $property->code);
    }
}
