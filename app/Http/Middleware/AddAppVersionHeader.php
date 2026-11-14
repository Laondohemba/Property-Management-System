<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every response carries X-App-Version so the SPA can detect version skew (spec §8.1, §4.4).
 */
class AddAppVersionHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-App-Version', (string) config('app.version'));

        return $response;
    }
}
