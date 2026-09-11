<?php

declare(strict_types=1);

namespace App\Services\Prayer;

use App\Enums\PrayerName;
use App\Exceptions\BusinessRuleException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reads the officially published Kemenag prayer schedule.
 *
 * This is used only to calibrate the local calculation — never to run it. The
 * whole point of computing times offline is that the adhan keeps working when
 * the network does not, so an outage here must cost nothing but the ability to
 * re-calibrate.
 */
class OfficialScheduleClient
{
    /** Kemenag times are fixed for a given date, so caching is safe and cheap. */
    private const CACHE_TTL = 86400;

    /** Maps this application's prayer names onto the API's Indonesian keys. */
    private const FIELD_MAP = [
        'fajr' => 'subuh',
        'sunrise' => 'terbit',
        'dhuhr' => 'dzuhur',
        'asr' => 'ashar',
        'maghrib' => 'maghrib',
        'isha' => 'isya',
    ];

    private function baseUrl(): string
    {
        return rtrim((string) config('dkm.official_schedule.base_url'), '/');
    }

    private function timeout(): int
    {
        return (int) config('dkm.official_schedule.timeout');
    }

    /**
     * Search the API's city list.
     *
     * @return array<int, array{id: string, label: string}>
     */
    public function searchCities(string $keyword): array
    {
        $keyword = trim($keyword);

        if (mb_strlen($keyword) < 3) {
            return [];
        }

        $response = Http::timeout($this->timeout())
            ->retry(2, 300)
            ->acceptJson()
            ->get($this->baseUrl().'/sholat/kota/cari/'.rawurlencode($keyword));

        if ($response->failed()) {
            throw new BusinessRuleException(
                'Tidak bisa menghubungi layanan jadwal resmi. Periksa koneksi internet lalu coba lagi.',
                503,
            );
        }

        return collect($response->json('data') ?? [])
            ->map(static fn (array $row): array => [
                'id' => (string) $row['id'],
                // The API shouts its city names; title case reads better in a list.
                'label' => mb_convert_case(mb_strtolower((string) $row['lokasi']), MB_CASE_TITLE, 'UTF-8'),
            ])
            ->all();
    }

    /**
     * The official timings for one city on one day, keyed by PrayerName value.
     *
     * @return array<string, string>|null Null when that day is unavailable.
     */
    public function timingsFor(string $cityId, CarbonImmutable $date): ?array
    {
        $cacheKey = sprintf('official-schedule:%s:%s', $cityId, $date->toDateString());

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($cityId, $date): ?array {
            $url = sprintf(
                '%s/sholat/jadwal/%s/%s/%s/%s',
                $this->baseUrl(),
                $cityId,
                $date->format('Y'),
                $date->format('m'),
                $date->format('d'),
            );

            try {
                $response = Http::timeout($this->timeout())->retry(2, 300)->acceptJson()->get($url);
            } catch (\Throwable $e) {
                Log::warning('Gagal mengambil jadwal resmi.', ['url' => $url, 'error' => $e->getMessage()]);

                return null;
            }

            if ($response->failed() || $response->json('status') !== true) {
                return null;
            }

            /** @var array<string, string>|null $jadwal */
            $jadwal = $response->json('data.jadwal');

            if ($jadwal === null) {
                return null;
            }

            $timings = [];

            foreach (PrayerName::cases() as $prayer) {
                $value = $jadwal[self::FIELD_MAP[$prayer->value]] ?? null;

                if (is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
                    $timings[$prayer->value] = $value;
                }
            }

            return $timings === [] ? null : $timings;
        });
    }

    /**
     * Every city the official schedule covers.
     *
     * 518 entries in one 21 KB response, so the whole list is fetched once and
     * cached rather than searched over the wire each time.
     *
     * @return array<int, array{id: string, label: string}>
     */
    public function allCities(): array
    {
        return Cache::remember('official-schedule-cities', self::CACHE_TTL, function (): array {
            try {
                $response = Http::timeout($this->timeout())
                    ->retry(2, 300)
                    ->acceptJson()
                    ->get($this->baseUrl().'/sholat/kota/semua');
            } catch (\Throwable $e) {
                Log::warning('Gagal mengambil daftar kota resmi.', ['error' => $e->getMessage()]);

                return [];
            }

            if ($response->failed()) {
                return [];
            }

            return collect($response->json('data') ?? [])
                ->map(static fn (array $city): array => [
                    'id' => (string) $city['id'],
                    'label' => Str::title(mb_strtolower((string) $city['lokasi'])),
                ])
                ->sortBy('label')
                ->values()
                ->all();
        }) ?? [];
    }

    /**
     * The city whose official schedule best fits a place name.
     *
     * Used after the browser reports a location: knowing the coordinates is not
     * enough once the published schedule is the primary source — the regency has
     * to be identified too, or the times would still be somebody else's.
     *
     * Matching is deliberately forgiving. Nominatim says "Purbalingga" where the
     * schedule says "Kab. Purbalingga", and neither is going to change to suit
     * the other.
     *
     * @param  array<int, string|null>  $candidates  Place names, most specific first.
     * @return array{id: string, label: string}|null
     */
    public function matchCity(array $candidates): ?array
    {
        $cities = $this->allCities();

        if ($cities === []) {
            return null;
        }

        foreach (array_filter($candidates) as $candidate) {
            $match = $this->bestMatchFor($candidate, $cities);

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Three passes, narrowest first.
     *
     * The type prefix matters and cannot simply be discarded: "Kota Bandung" and
     * "Kab. Bandung" are different places with different times, and stripping
     * both to "bandung" would hand back whichever happened to be listed first.
     * So an exact match including the type wins outright; only after that does
     * the bare name get a look, and even then a candidate that stated its type
     * will not be answered with the other one.
     *
     * @param  array<int, array{id: string, label: string}>  $cities
     * @return array{id: string, label: string}|null
     */
    private function bestMatchFor(string $candidate, array $cities): ?array
    {
        $full = $this->normaliseName($candidate, keepType: true);
        $bare = $this->normaliseName($candidate);
        $wantedType = $this->typeOf($candidate);

        if ($bare === '') {
            return null;
        }

        // 1. Same name and same type.
        foreach ($cities as $city) {
            if ($this->normaliseName($city['label'], keepType: true) === $full) {
                return $city;
            }
        }

        // 2. Same bare name. A candidate with no type of its own takes whatever
        //    matches; one that named a type only accepts that type.
        foreach ($cities as $city) {
            $sameName = $this->normaliseName($city['label']) === $bare;
            $typeOk = $wantedType === null || $this->typeOf($city['label']) === $wantedType;

            if ($sameName && $typeOk) {
                return $city;
            }
        }

        // 3. City contains the candidate, so "Purbalingga" reaches
        //    "Kab. Purbalingga" and "Bandung Barat" reaches "Kab. Bandung Barat"
        //    rather than plain "Kab. Bandung".
        foreach ($cities as $city) {
            $typeOk = $wantedType === null || $this->typeOf($city['label']) === $wantedType;

            if ($typeOk && str_contains($this->normaliseName($city['label']), $bare)) {
                return $city;
            }
        }

        /*
         * 4. The other direction: the candidate contains the city name.
         *
         * The published schedule is per kabupaten/kota, but a place name can be
         * a subdivision of one — "Jakarta Pusat" against a list that only says
         * "Kota Jakarta". Longest name first, so a candidate that could match
         * several is answered with the most specific.
         */
        $byLength = $cities;
        usort($byLength, fn (array $a, array $b): int => mb_strlen($b['label']) <=> mb_strlen($a['label']));

        foreach ($byLength as $city) {
            $name = $this->normaliseName($city['label']);

            // Two characters would match almost anything.
            if (mb_strlen($name) >= 4 && str_contains($bare, $name)) {
                return $city;
            }
        }

        return null;
    }

    /**
     * `kab` or `kota` when the name says so, null when it does not.
     */
    private function typeOf(string $name): ?string
    {
        $name = mb_strtolower(trim($name));

        if (preg_match('/^(kabupaten|kab\.?)\s+/u', $name) === 1) {
            return 'kab';
        }

        if (preg_match('/^(kota administrasi|kotamadya|kota)\s+/u', $name) === 1) {
            return 'kota';
        }

        return null;
    }

    /**
     * Lowercased and stripped of punctuation, and of the type prefix unless
     * `$keepType` says the prefix is part of what is being compared.
     */
    private function normaliseName(string $name, bool $keepType = false): string
    {
        $name = mb_strtolower(trim($name));

        if (! $keepType) {
            $name = preg_replace('/^(kabupaten|kab\.?|kota administrasi|kotamadya|kota)\s+/u', '', $name) ?? $name;
        } else {
            // Normalise the prefixes that mean the same thing before comparing.
            $name = preg_replace('/^kabupaten\s+/u', 'kab ', $name) ?? $name;
            $name = preg_replace('/^(kota administrasi|kotamadya)\s+/u', 'kota ', $name) ?? $name;
        }

        return trim(preg_replace('/[^a-z0-9 ]/u', ' ', $name) ?? $name);
    }

    /**
     * A whole month in one request, keyed by date.
     *
     * The API answers a month at a time, so asking day by day would be thirty
     * requests for data it already sent as one — and thirty chances for a free
     * community API to rate-limit us.
     *
     * @return array<string, array<string, string>>|null `Y-m-d` => timings
     */
    public function monthlyTimings(string $cityId, CarbonImmutable $month): ?array
    {
        $cacheKey = sprintf('official-schedule-month:%s:%s', $cityId, $month->format('Y-m'));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($cityId, $month): ?array {
            $url = sprintf(
                '%s/sholat/jadwal/%s/%s/%s',
                $this->baseUrl(),
                $cityId,
                $month->format('Y'),
                $month->format('n'),
            );

            try {
                $response = Http::timeout($this->timeout())->retry(2, 300)->acceptJson()->get($url);
            } catch (\Throwable $e) {
                Log::warning('Gagal mengambil jadwal resmi bulanan.', ['url' => $url, 'error' => $e->getMessage()]);

                return null;
            }

            if ($response->failed() || $response->json('status') !== true) {
                return null;
            }

            $days = $response->json('data.jadwal');

            if (! is_array($days)) {
                return null;
            }

            $byDate = [];

            foreach ($days as $day) {
                $date = $day['date'] ?? null;

                if (! is_string($date)) {
                    continue;
                }

                $timings = [];

                foreach (PrayerName::cases() as $prayer) {
                    $value = $day[self::FIELD_MAP[$prayer->value]] ?? null;

                    if (is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
                        $timings[$prayer->value] = $value;
                    }
                }

                if ($timings !== []) {
                    $byDate[$date] = $timings;
                }
            }

            return $byDate === [] ? null : $byDate;
        });
    }

    /**
     * The city name the API knows this id by, for showing back to the user.
     */
    public function cityLabel(string $cityId, CarbonImmutable $date): ?string
    {
        $cacheKey = sprintf('official-schedule-label:%s', $cityId);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($cityId, $date): ?string {
            $url = sprintf(
                '%s/sholat/jadwal/%s/%s/%s/%s',
                $this->baseUrl(),
                $cityId,
                $date->format('Y'),
                $date->format('m'),
                $date->format('d'),
            );

            try {
                $response = Http::timeout($this->timeout())->acceptJson()->get($url);
            } catch (\Throwable) {
                return null;
            }

            $label = $response->json('data.lokasi');

            return is_string($label)
                ? mb_convert_case(mb_strtolower($label), MB_CASE_TITLE, 'UTF-8')
                : null;
        });
    }
}
