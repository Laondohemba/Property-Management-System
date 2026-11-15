<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\Problem;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key for POSTs with money or inventory effects (spec §8.1).
 *
 * The first request with a key runs and, when it succeeds, its response is kept for 24 hours
 * in Redis (shared by every pod). Repeats with the same key and body get that response back,
 * marked Idempotent-Replayed, without running again: a double click or a retry after a
 * dropped connection cannot create a second block or booking. The same key with a different
 * body is refused, and a repeat that arrives while the first is still running gets 409.
 */
class EnsureIdempotency
{
    public const TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() !== 'POST') {
            return $next($request);
        }

        $key = (string) $request->header('Idempotency-Key', '');

        if ($key === '' || strlen($key) > 255) {
            return Problem::response(400, 'idempotency_key_required', 'Idempotency key required',
                'Send a unique Idempotency-Key header with this request so it is safe to retry.');
        }

        $cacheKey = 'idempotency:'.hash('sha256', implode('|', [
            (string) ($request->user()?->getAuthIdentifier() ?? 'guest'),
            $request->method(),
            $request->path(),
            $key,
        ]));
        $fingerprint = $this->fingerprint($request);

        if (($stored = $this->stored($cacheKey)) !== null) {
            return $this->replay($stored, $fingerprint);
        }

        $lock = Cache::lock($cacheKey.':lock', 30);

        if (! $lock->get()) {
            return Problem::response(409, 'idempotency_in_progress', 'Request in progress',
                'The same request is still being processed. Wait a moment, then reload.');
        }

        try {
            // Another pod may have finished the same request while this one waited for the lock.
            if (($finished = $this->stored($cacheKey)) !== null) {
                return $this->replay($finished, $fingerprint);
            }

            $response = $next($request);

            // Only completed work is replayed; a failed attempt can be retried with the same key.
            if ($response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'content' => (string) $response->getContent(),
                    'content_type' => (string) $response->headers->get('Content-Type', 'application/json'),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * The stored response, read fresh each time: the shared cache can change between calls.
     *
     * @phpstan-impure
     *
     * @return array<string, mixed>|null
     */
    private function stored(string $cacheKey): ?array
    {
        $value = Cache::get($cacheKey);

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function replay(array $stored, string $fingerprint): Response
    {
        if (($stored['fingerprint'] ?? null) !== $fingerprint) {
            return Problem::response(422, 'idempotency_key_reused', 'Idempotency key reused',
                'This Idempotency-Key was already used for a different request.');
        }

        return new Response((string) $stored['content'], (int) $stored['status'], [
            'Content-Type' => (string) $stored['content_type'],
            'Idempotent-Replayed' => 'true',
        ]);
    }

    private function fingerprint(Request $request): string
    {
        $content = $request->getContent();

        if ($content === '' || $request->files->count() > 0) {
            $files = array_map(
                fn (mixed $file): string => $file instanceof UploadedFile ? (string) hash_file('sha256', $file->getRealPath() ?: '') : '',
                $request->allFiles(),
            );
            $content = json_encode([$request->post(), $files]) ?: '';
        }

        return hash('sha256', $content);
    }
}
