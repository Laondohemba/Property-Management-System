<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\BuildSessionView;
use App\Modules\Identity\DTOs\SessionMetaData;
use App\Support\Http\Bff;
use App\Support\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /bff/session: everything the SPA shell needs after sign-in (spec §8.2).
 */
class SessionController extends Controller
{
    public function __invoke(Request $request, PropertyContext $context, BuildSessionView $view): JsonResponse
    {
        return Bff::respond(
            $view->handle($request->user(), $context->property()),
            new SessionMetaData((string) config('app.version')),
        );
    }
}
