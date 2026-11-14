<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the compiled Angular shell for any non-API GET request so deep links work.
 */
class SpaController extends Controller
{
    /**
     * Path prefixes that belong to the server and must never fall back to the SPA (spec §11.2).
     */
    private const SERVER_PREFIXES = ['bff', 'integrations', 'webhooks', 'broadcasting', 'sanctum', 'up'];

    public function __invoke(Request $request): Response
    {
        $firstSegment = (string) $request->segment(1);

        if (in_array($firstSegment, self::SERVER_PREFIXES, true)) {
            abort(404);
        }

        $index = public_path('index.html');

        if (! is_file($index)) {
            return response(
                'The Angular application has not been built. Run "npm run build", or use "npm run dev" and open http://localhost:4200.',
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        return response()->file($index, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
