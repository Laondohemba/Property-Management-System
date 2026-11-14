<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\Contracts\BaseData;

/**
 * BFF responses in the { data, meta } envelope (spec §8.1), built from typed DTOs so the
 * generated TypeScript contract (ARC-11) matches what is sent.
 */
final class Bff
{
    /**
     * @param  BaseData|array<int, BaseData>  $data
     */
    public static function respond(BaseData|array $data, ?BaseData $meta = null, int $status = 200): JsonResponse
    {
        $body = ['data' => $data];

        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $status);
    }
}
