<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\PrayerName;
use App\Models\PrayerSchedule;
use App\Services\Prayer\PrayerScheduleService;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The month table on the prayer schedule page.
 *
 * Stepping to the next month used to reload the whole page: the header, the
 * sidebar, today's card, all of it — to change thirty rows in one table. This
 * component replaces just the table, which is the only thing that differs
 * between one month and the next.
 *
 * `#[Url]` keeps `?month=` in the address bar in step with the property, so the
 * view still survives a refresh and can still be linked to or printed.
 */
class PrayerMonthTable extends Component
{
    /**
     * The month being shown, as `Y-m`.
     *
     * A string rather than a Carbon because Livewire serialises this between
     * requests, and a plain string is what the query parameter holds anyway.
     */
    #[Url(as: 'month', keep: true)]
    public string $month = '';

    public bool $isAdmin = false;

    /**
     * `$month` arrives as a string, never null: Livewire assigns matching
     * parameters straight onto the typed property before this runs, so a null
     * from `request()->query()` would fail there rather than here.
     */
    public function mount(string $month = '', bool $isAdmin = false): void
    {
        $this->isAdmin = $isAdmin;
        $this->month = DateHelper::normaliseMonth($month !== '' ? $month : $this->month);
    }

    public function previousMonth(): void
    {
        $this->month = $this->carbonMonth()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->carbonMonth()->addMonth()->format('Y-m');
    }

    /**
     * Runs when the month input is changed by hand.
     *
     * A value typed straight into the browser's month picker can be anything,
     * so it is put back through the same normaliser as the initial mount — the
     * one in DateHelper, shared with the public month browser.
     */
    public function updatedMonth(string $value): void
    {
        $this->month = DateHelper::normaliseMonth($value);
    }

    public function render(PrayerScheduleService $schedules): View
    {
        $month = $this->carbonMonth();

        return view('livewire.prayer-month-table', [
            'monthDate' => $month,
            'schedules' => $this->rowsFor($schedules, $month),
            'prayers' => PrayerName::cases(),
            'todayDate' => DateHelper::today()->toDateString(),
        ]);
    }

    /**
     * @return Collection<int, PrayerSchedule>
     */
    private function rowsFor(PrayerScheduleService $schedules, CarbonImmutable $month): Collection
    {
        return $schedules->forMonth($month);
    }

    private function carbonMonth(): CarbonImmutable
    {
        return DateHelper::startOfMonthFrom($this->month);
    }
}
