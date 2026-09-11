<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Helpers\Flash;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivating an account has to take effect on the next request, not on the
 * next login — otherwise a departed employee keeps their session for hours.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Flash::error('Akun Anda dinonaktifkan. Silakan hubungi pengurus DKM.');

            return redirect()->route('login');
        }

        return $next($request);
    }
}
