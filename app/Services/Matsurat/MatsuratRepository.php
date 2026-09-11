<?php

declare(strict_types=1);

namespace App\Services\Matsurat;

use App\Enums\MatsuratTime;
use App\Enums\MatsuratVariant;
use App\Support\Helpers\DateHelper;
use Illuminate\Support\Facades\Cache;

/**
 * Al-Ma'tsurat, read from the JSON that ships with the repository.
 *
 * No API exists for this and none is likely to. Al-Ma'tsurat is not a set of
 * supplications — it is a specific order and specific repetition counts, and
 * every doa API available models supplications as standalone entries with
 * neither. Assembling one from such an API would mean deciding the sequence and
 * the counts myself, which is a religious judgement rather than a technical one.
 *
 * Because the text lives in the repository, this page depends on nothing
 * external at all: it works with the network unplugged.
 *
 * The source is the copy the office actually reads from. Its shape:
 *
 *     { title, description, data: [ entry, … ] }
 *
 * where an entry is either a `single` — one supplication, read `repeat` times —
 * or a `parent`, whose own `arabic`/`latin`/`translation` name a surah and
 * whose nested `data` holds that surah's verses one by one.
 */
class MatsuratRepository
{
    /**
     * Parsed once and kept.
     *
     * The mushaf does not change between requests, and parsing 60 KB of JSON on
     * every page view to produce the same array is work for its own sake.
     */
    private const CACHE_TTL = 604800;

    private const BASE_PATH = 'data/almatsurat';

    /**
     * One reading, ready to render.
     *
     * @return array{
     *     title: string,
     *     items: array<int, array<string, mixed>>,
     *     total_readings: int,
     *     countable: int
     * }
     */
    public function reading(MatsuratVariant $variant, MatsuratTime $time): array
    {
        $key = sprintf('matsurat:%s:%s', $variant->value, $time->value);

        return Cache::remember($key, self::CACHE_TTL, function () use ($variant, $time): array {
            $document = $this->load($variant, $time);
            $entries = is_array($document['data'] ?? null) ? $document['data'] : [];

            $items = [];
            $order = 0;
            $totalReadings = 0;
            $countable = 0;

            foreach ($entries as $entry) {
                $verses = $this->verses($entry);
                $arabic = $this->clean((string) ($entry['arabic'] ?? ''));

                // Nothing to read, and nothing a number beside it would mean.
                if ($verses === [] && $arabic === '') {
                    continue;
                }

                $repeat = max(1, (int) ($entry['repeat'] ?? 1));

                $order++;
                $totalReadings += $repeat;
                $countable += $repeat > 1 ? 1 : 0;

                $items[] = [
                    'order' => $order,
                    'title' => $this->clean((string) ($entry['title'] ?? '')),
                    'repeat' => $repeat,

                    /*
                     * A surah's own `arabic` is its name, not its text — the
                     * text is in `verses`. Printing the name in the body would
                     * put "البقرة" above the ayat as though it were the first
                     * line of them.
                     */
                    'arabic' => $verses === [] ? $arabic : '',
                    'latin' => $verses === [] ? $this->clean((string) ($entry['latin'] ?? '')) : '',
                    'translation' => $verses === [] ? $this->clean((string) ($entry['translation'] ?? '')) : '',

                    // Shown as a subtitle instead: "Al-Baqarah · Sapi Betina".
                    'source' => $verses === [] ? null : $this->source($entry),
                    'verses' => $verses,
                ];
            }

            return [
                'title' => $this->clean((string) ($document['title'] ?? "Al-Ma'tsurat")),
                'items' => $items,
                // What "finished" means: every repetition of every item, not the
                // number of items — reading one line a hundred times is the bulk
                // of the work and a per-item count would hide that.
                'total_readings' => $totalReadings,
                'countable' => $countable,
            ];
        });
    }

    /**
     * Which reading fits the time of day.
     *
     * Morning until Dhuhr, evening after. Someone opening the page at four in
     * the afternoon wants the evening dhikr, and making them choose first is a
     * step the clock can take for them.
     */
    public function suggestedTime(?string $dhuhr = null): MatsuratTime
    {
        $now = DateHelper::now();
        $noon = $dhuhr !== null
            ? DateHelper::combine($now, $dhuhr)
            : $now->startOfDay()->addHours(12);

        return $now->lessThan($noon) ? MatsuratTime::Pagi : MatsuratTime::Petang;
    }

    /**
     * The verses of a surah entry, or an empty array for a plain supplication.
     *
     * @param  array<string, mixed>  $entry
     * @return array<int, array{title: string, arabic: string, latin: string, translation: string}>
     */
    private function verses(array $entry): array
    {
        $rows = is_array($entry['data'] ?? null) ? $entry['data'] : [];
        $verses = [];

        foreach ($rows as $row) {
            $arabic = $this->clean((string) ($row['arabic'] ?? ''));

            if ($arabic === '') {
                continue;
            }

            $verses[] = [
                'title' => $this->clean((string) ($row['title'] ?? '')),
                'arabic' => $this->markAyahEnd($arabic),
                'latin' => $this->clean((string) ($row['latin'] ?? '')),
                'translation' => $this->clean((string) ($row['translation'] ?? '')),
            ];
        }

        return $verses;
    }

    /**
     * Put the ayah number inside the Qur'anic end-of-ayah rosette.
     *
     * The source closes each verse with a bare Arabic-Indic numeral. U+06DD —
     * ARABIC END OF AYAH — is the character that draws the ornament, and it
     * encloses whatever digits follow it, so prefixing the numeral is all it
     * takes. Scheherazade New, loaded by `partials.portal.quran-font` which
     * this page already pushes, draws it properly.
     *
     * Worth doing rather than leaving bare digits: the rosette is not
     * decoration but the convention every printed mushaf uses, and a reader
     * following along in one looks for it to find their place. The text that
     * shipped before carried the ornament but no number inside it, which is the
     * half that helps least.
     *
     * All 224 verses across the four documents carry their digits only at the
     * very end, so anchoring there is safe. The `u` modifier is what makes the
     * range mean codepoints rather than bytes.
     */
    private function markAyahEnd(string $arabic): string
    {
        /*
         * The replacement is double-quoted on purpose: `\x{...}` denotes a
         * codepoint inside a *pattern*, but is plain text in a replacement, so
         * the ornament has to arrive as the character itself.
         */
        return (string) preg_replace(
            '/\s*([\x{0660}-\x{0669}]+)\s*$/u',
            " \u{06DD}\$1",
            $arabic,
        );
    }

    /**
     * A surah's name and what it means, on one line.
     *
     * @param  array<string, mixed>  $entry
     */
    private function source(array $entry): ?string
    {
        $parts = array_filter([
            $this->clean((string) ($entry['latin'] ?? '')),
            $this->clean((string) ($entry['translation'] ?? '')),
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(MatsuratVariant $variant, MatsuratTime $time): array
    {
        $path = resource_path(sprintf(
            '%s/%s-%s.json',
            self::BASE_PATH,
            $variant->value,
            $time->value,
        ));

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Older copies of this text escape apostrophes and ampersands as HTML
     * entities, so "Ma&apos;tsurat" would otherwise be printed literally by
     * Blade — which escapes on output and would show the entity rather than the
     * character. Kept for whatever the next copy turns out to contain.
     */
    private function clean(string $value): string
    {
        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
