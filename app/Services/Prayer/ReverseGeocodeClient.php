<?php

declare(strict_types=1);

namespace App\Services\Prayer;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns coordinates into a readable address.
 *
 * Called from the server rather than the browser for two reasons: Nominatim's
 * usage policy asks callers to identify themselves with a User-Agent, which a
 * browser cannot set, and answers can be cached here instead of re-asking on
 * every click.
 *
 * A failure is never fatal — the coordinates are what the schedule is computed
 * from, and the address is only there so a human can confirm the right place was
 * picked.
 */
class ReverseGeocodeClient
{
    /** Coordinates rounded to ~11 m; a building does not move. */
    private const CACHE_TTL = 2592000;

    private const COORDINATE_PRECISION = 4;

    /**
     * A human-readable address, or null when the lookup is unavailable.
     */
    public function describe(float $latitude, float $longitude): ?string
    {
        $key = sprintf(
            'reverse-geocode:%s,%s',
            number_format($latitude, self::COORDINATE_PRECISION, '.', ''),
            number_format($longitude, self::COORDINATE_PRECISION, '.', ''),
        );

        return Cache::remember($key, self::CACHE_TTL, function () use ($latitude, $longitude): ?string {
            try {
                $response = Http::timeout((int) config('dkm.reverse_geocode.timeout'))
                    ->withHeaders([
                        // Nominatim's policy requires an identifiable caller.
                        'User-Agent' => (string) config('dkm.reverse_geocode.user_agent'),
                    ])
                    ->acceptJson()
                    ->get(rtrim((string) config('dkm.reverse_geocode.base_url'), '/').'/reverse', [
                        'format' => 'jsonv2',
                        'lat' => $latitude,
                        'lon' => $longitude,
                        // Street level: enough to recognise the place without
                        // exposing a precise house number in a shared setting.
                        'zoom' => 16,
                        'addressdetails' => 1,
                        'accept-language' => 'id',
                    ]);
            } catch (\Throwable $e) {
                Log::info('Reverse geocode gagal.', ['error' => $e->getMessage()]);

                return null;
            }

            if ($response->failed()) {
                return null;
            }

            /** @var array<string, string>|null $parts */
            $parts = $response->json('address');

            return $parts === null
                ? $response->json('display_name')
                : $this->compose($parts);
        });
    }

    /**
     * The raw address components, cached alongside the composed line so asking
     * for both costs one request.
     *
     * @return array<string, string>|null
     */
    private function addressParts(float $latitude, float $longitude): ?array
    {
        $key = sprintf(
            'reverse-geocode-parts:%s,%s',
            number_format($latitude, self::COORDINATE_PRECISION, '.', ''),
            number_format($longitude, self::COORDINATE_PRECISION, '.', ''),
        );

        return Cache::remember($key, self::CACHE_TTL, function () use ($latitude, $longitude): ?array {
            try {
                $response = Http::timeout((int) config('dkm.reverse_geocode.timeout'))
                    ->withHeaders(['User-Agent' => (string) config('dkm.reverse_geocode.user_agent')])
                    ->acceptJson()
                    ->get(rtrim((string) config('dkm.reverse_geocode.base_url'), '/').'/reverse', [
                        'format' => 'jsonv2',
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'zoom' => 16,
                        'addressdetails' => 1,
                        'accept-language' => 'id',
                    ]);
            } catch (\Throwable $e) {
                Log::info('Reverse geocode gagal.', ['error' => $e->getMessage()]);

                return null;
            }

            if ($response->failed()) {
                return null;
            }

            $parts = $response->json('address');

            return is_array($parts) ? $parts : null;
        });
    }

    /**
     * The place names for a coordinate, most specific first.
     *
     * Kept separate from `describe()` because the two want different things:
     * that one wants a line a human reads, this one wants candidates to match
     * against the official city list.
     *
     * @return array<int, string>
     */
    public function placeNames(float $latitude, float $longitude): array
    {
        $parts = $this->addressParts($latitude, $longitude);

        if ($parts === null) {
            return [];
        }

        return array_values(array_filter([
            $parts['city'] ?? null,
            $parts['town'] ?? null,
            $parts['municipality'] ?? null,
            // Nominatim uses `county` for an Indonesian kabupaten, which is
            // exactly the level the official schedule is published at.
            $parts['county'] ?? null,
            $parts['city_district'] ?? null,
            $parts['state'] ?? null,
        ]));
    }

    /**
     * Builds an Indonesian-style address from the pieces Nominatim returns.
     *
     * `display_name` is used as-is only when nothing recognisable is present:
     * it tends to repeat the province and append the country, which reads oddly
     * on a form that is only ever about one office.
     *
     * @param  array<string, string>  $parts
     */
    private function compose(array $parts): ?string
    {
        $ordered = [
            $parts['road'] ?? null,
            $parts['neighbourhood'] ?? $parts['hamlet'] ?? null,
            $parts['village'] ?? $parts['suburb'] ?? null,
            $parts['city_district'] ?? $parts['municipality'] ?? null,
            $parts['city'] ?? $parts['town'] ?? $parts['county'] ?? null,
            $parts['state'] ?? null,
            $parts['postcode'] ?? null,
        ];

        $address = collect($ordered)
            ->filter()
            // Nominatim often repeats a name across levels ("Purbalingga" as
            // both city and county); showing it twice looks like a bug.
            ->unique()
            ->implode(', ');

        return $address === '' ? null : $address;
    }
}
