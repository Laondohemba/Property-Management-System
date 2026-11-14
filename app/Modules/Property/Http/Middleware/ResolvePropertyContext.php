<?php

declare(strict_types=1);

namespace App\Modules\Property\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Support\Http\Problem;
use App\Support\PropertyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates X-Property-Id against the user's assigned properties on every request (spec §2.7).
 *
 * Modes:
 *  - required (default): header must name an accessible, active property.
 *  - optional: an accessible property is used when given; otherwise no property.
 *  - default:  like optional, but falls back to the user's first accessible property
 *              (used by GET /bff/session so the SPA can bootstrap).
 */
class ResolvePropertyContext
{
    public function __construct(private readonly PropertyContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $publicId = $request->header('X-Property-Id');
        $property = $publicId ? $user->accessibleProperties()->where('properties.public_id', $publicId)->first() : null;

        if ($property === null && $mode === 'required') {
            return $publicId
                ? Problem::response(403, 'property_forbidden', 'Forbidden', 'You do not have access to this property.')
                : Problem::response(400, 'property_required', 'Property required', 'Select a property (X-Property-Id header) to use this endpoint.');
        }

        if ($property === null && $mode === 'default') {
            $property = $user->accessibleProperties()->orderBy('properties.name')->first();
        }

        $this->context->set($property);

        return $next($request);
    }
}
