<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 9457 problem+json responses with a stable machine-readable code (spec §8.4).
 */
final class Problem
{
    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    public static function response(
        int $status,
        string $code,
        string $title,
        ?string $detail = null,
        array $extra = [],
        array $headers = [],
    ): JsonResponse {
        $body = array_filter([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
        ], fn (mixed $value): bool => $value !== null) + $extra;

        return new JsonResponse($body, $status, ['Content-Type' => 'application/problem+json'] + $headers);
    }

    /**
     * Human-readable title for a status, including Laravel's non-standard 419.
     */
    public static function titleFor(int $status): string
    {
        return match ($status) {
            419 => 'Session expired',
            423 => 'Password confirmation required',
            default => Response::$statusTexts[$status] ?? 'Error',
        };
    }

    /**
     * Map an HTTP status to the error codes defined in spec §8.4.
     */
    public static function codeFor(int $status): string
    {
        return match ($status) {
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            412 => 'stale_version',
            419 => 'session_expired',
            422 => 'validation_failed',
            423 => 'password_confirmation_required',
            429 => 'rate_limited',
            502, 503 => 'upstream_unavailable',
            default => $status >= 500 ? 'server_error' : 'http_error',
        };
    }
}
