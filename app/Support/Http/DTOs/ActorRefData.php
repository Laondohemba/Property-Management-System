<?php

declare(strict_types=1);

namespace App\Support\Http\DTOs;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

class ActorRefData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromUser(?Model $user): ?self
    {
        return $user === null ? null : new self((string) $user->getAttribute('public_id'), (string) $user->getAttribute('name'));
    }
}
