<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline hardening headers applied to every response.
 */
class SetSecurityHeaders
{
    /**
     * `Permissions-Policy` is written out per feature on purpose.
     *
     * An empty allowlist — `geolocation=()` — disables the feature for every
     * origin including this one, and it overrides whatever the browser's own
     * settings say. That is stricter than it looks: it silently kills the
     * "Deteksi Lokasi Saya" button with no prompt and no error the user can act
     * on, which is exactly what it did until this was spotted.
     *
     * So each entry states which features this application genuinely uses:
     *
     *   camera=(self)      QR check-in scans the attendance code
     *   geolocation=(self) Settings fills the mosque coordinates
     *   microphone=()      never used — stays off
     *
     * @var array<string, string>
     */
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(self)',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $header => $value) {
            $response->headers->set($header, $value);
        }

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
