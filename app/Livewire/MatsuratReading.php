<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\MatsuratTime;
use App\Enums\MatsuratVariant;
use App\Enums\PrayerName;
use App\Services\Matsurat\MatsuratRepository;
use App\Services\Prayer\PrayerScheduleService;
use App\Support\Helpers\PublicSchedule;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The Al-Ma'tsurat reading, and the controls that switch it.
 *
 * A component rather than a page reload because the reader may be halfway down
 * a hundred-repetition dhikr: replacing the document would throw away their
 * place on the page, which is the one thing this page cannot afford to lose.
 *
 * `#[Url]` keeps `?jenis=` and `?waktu=` in the address bar in step with the
 * properties, so a particular reading is still linkable, refreshable and
 * bookmarkable — the switching is faster, not less addressable.
 */
class MatsuratReading extends Component
{
    #[Url(as: 'jenis', keep: true)]
    public string $variant = '';

    #[Url(as: 'waktu', keep: true)]
    public string $time = '';

    public function mount(): void
    {
        $this->variant = $this->resolvedVariant()->value;

        // Left blank on a first visit so the clock can answer it; once the
        // reader chooses, their choice is what the URL carries.
        $this->time = $this->resolvedTime()->value;
    }

    public function switchVariant(string $variant): void
    {
        $this->variant = (MatsuratVariant::tryFrom($variant) ?? MatsuratVariant::Sugro)->value;
    }

    public function switchTime(string $time): void
    {
        $this->time = (MatsuratTime::tryFrom($time) ?? MatsuratTime::Pagi)->value;
    }

    public function render(MatsuratRepository $matsurat): View
    {
        $variant = $this->resolvedVariant();
        $time = $this->resolvedTime();

        /*
         * Named apart from the `$variant` / `$time` properties on purpose.
         *
         * Livewire hands its public properties to the view as well, and those
         * are the raw strings from the URL — a same-named enum passed here
         * would be shadowed by them, and `$variant->value` would then be called
         * on a string.
         */
        return view('livewire.matsurat-reading', [
            'reading' => $matsurat->reading($variant, $time),
            'currentVariant' => $variant,
            'currentTime' => $time,
            'variants' => MatsuratVariant::cases(),
            'times' => MatsuratTime::cases(),
        ]);
    }

    private function resolvedVariant(): MatsuratVariant
    {
        return MatsuratVariant::tryFrom($this->variant) ?? MatsuratVariant::Sugro;
    }

    /**
     * The reader's choice, or the one the time of day implies.
     *
     * Someone opening this at four in the afternoon wants the evening dhikr,
     * and the prayer schedule already knows when Dhuhr is — so asking them to
     * choose first would be asking for something the clock can answer.
     */
    private function resolvedTime(): MatsuratTime
    {
        $chosen = MatsuratTime::tryFrom($this->time);

        if ($chosen !== null) {
            return $chosen;
        }

        $schedules = app(PrayerScheduleService::class);
        $dhuhr = PublicSchedule::time($schedules->today(), PrayerName::Dhuhr);

        return app(MatsuratRepository::class)->suggestedTime($dhuhr);
    }
}
