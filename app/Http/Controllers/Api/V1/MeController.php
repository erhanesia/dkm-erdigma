<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user.
 *
 * `me` is a genuine singleton — it names whoever holds the current session, so
 * it stays singular where every other resource in this API is plural. It is also
 * why no id is accepted: a caller can never address anyone but themselves.
 *
 * The web interface does not call this. Pages are rendered by Blade with
 * `auth()->user()` already in hand, so fetching the same data over HTTP would be
 * a round trip for something the server had before it wrote the HTML. This
 * exists for clients that have no such luxury — the player agent, and anything
 * built against this API later.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Belum masuk.', status: 401);
        }

        return ApiResponse::success(
            new UserResource($user),
        );
    }
}
