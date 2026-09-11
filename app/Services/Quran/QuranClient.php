<?php

declare(strict_types=1);

namespace App\Services\Quran;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Qur'an text, translation and recitation from equran.id.
 *
 * Cached for a month, which is not a performance tweak but the point: the mushaf
 * does not change, so after the first request a surah is served from cache and
 * the page no longer depends on anyone else's server being up. A mosque site
 * that cannot open Al-Fatihah because a third party is down would be a poor
 * trade for text that has been fixed for fourteen centuries.
 *
 * Every method answers null on failure rather than throwing. The caller shows an
 * honest message; nothing here is important enough to break a page over.
 */
class QuranClient
{
    /**
     * All 114 surahs, for the index.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function surahs(): Collection
    {
        $data = $this->fetch('quran:surahs', '/surat');

        return collect($data ?? [])->map(fn (array $surah): array => [
            'number' => (int) $surah['nomor'],
            'name' => (string) $surah['nama'],
            'latin' => (string) $surah['namaLatin'],
            'meaning' => (string) $surah['arti'],
            'verses' => (int) $surah['jumlahAyat'],
            'revealed' => (string) $surah['tempatTurun'],
        ]);
    }

    /**
     * One surah with all of its verses.
     *
     * @return array<string, mixed>|null
     */
    public function surah(int $number): ?array
    {
        if ($number < 1 || $number > 114) {
            return null;
        }

        $data = $this->fetch('quran:surah:'.$number, '/surat/'.$number);

        if ($data === null) {
            return null;
        }

        return [
            'number' => (int) $data['nomor'],
            'name' => (string) $data['nama'],
            'latin' => (string) $data['namaLatin'],
            'meaning' => (string) $data['arti'],
            'verses' => (int) $data['jumlahAyat'],
            'revealed' => (string) $data['tempatTurun'],
            // The API sends HTML in the description; the view strips it, but the
            // raw value is kept so a future page could render it properly.
            'description' => (string) ($data['deskripsi'] ?? ''),
            'audio' => $this->reciterAudio($data['audioFull'] ?? []),
            'previous' => $this->neighbour($data['suratSebelumnya'] ?? null),
            'next' => $this->neighbour($data['suratSelanjutnya'] ?? null),
            'ayahs' => collect($data['ayat'] ?? [])->map(fn (array $ayah): array => [
                'number' => (int) $ayah['nomorAyat'],
                'arabic' => (string) $ayah['teksArab'],
                'latin' => (string) $ayah['teksLatin'],
                'translation' => (string) $ayah['teksIndonesia'],
                'audio' => $this->reciterAudio($ayah['audio'] ?? []),
            ])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function reciters(): array
    {
        return (array) config('dkm.quran.reciters');
    }

    /**
     * The chosen reciter's track, falling back to whatever is available.
     *
     * @param  array<string, string>|mixed  $audio
     */
    private function reciterAudio(mixed $audio): ?string
    {
        if (! is_array($audio) || $audio === []) {
            return null;
        }

        $preferred = (string) config('dkm.quran.default_reciter');

        return $audio[$preferred] ?? (string) reset($audio);
    }

    /**
     * equran.id sends `false` rather than null at the two ends of the mushaf.
     *
     * @return array{number: int, latin: string}|null
     */
    private function neighbour(mixed $surah): ?array
    {
        if (! is_array($surah)) {
            return null;
        }

        return [
            'number' => (int) $surah['nomor'],
            'latin' => (string) $surah['namaLatin'],
        ];
    }

    /**
     * @return array<mixed>|null
     */
    private function fetch(string $key, string $path): ?array
    {
        return Cache::remember($key, (int) config('dkm.quran.cache_ttl'), function () use ($path): ?array {
            try {
                $response = Http::timeout((int) config('dkm.quran.timeout'))
                    ->acceptJson()
                    ->get(rtrim((string) config('dkm.quran.base_url'), '/').$path);
            } catch (\Throwable $e) {
                Log::info('Gagal mengambil data Al-Quran.', ['path' => $path, 'error' => $e->getMessage()]);

                return null;
            }

            if ($response->failed()) {
                return null;
            }

            $data = $response->json('data');

            return is_array($data) ? $data : null;
        });
    }
}
