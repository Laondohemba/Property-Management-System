<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Resources\SessionResource;
use Illuminate\Http\Request;

/**
 * GET /bff/session: everything the SPA shell needs after sign-in (spec §8.2).
 */
class SessionController extends Controller
{
    public function __invoke(Request $request): SessionResource
    {
        $user = $request->user()->loadMissing(['organization', 'roles']);

        return (new SessionResource($user))->additional([
            'meta' => ['app_version' => (string) config('app.version')],
        ]);
    }
}
