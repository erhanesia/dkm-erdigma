<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\PrayerName;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Prayer\OfficialScheduleClient;
use App\Services\Prayer\PrayerScheduleService;
use App\Services\SettingService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\PublicSchedule;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The month table on the public prayer page, with its own region and month.
 *
 * Both used to be plain links and a GET form, so changing the kabupaten
 * reloaded the entire page — the hero, the countdown, today's strip, the footer
 * — to redraw thirty rows in one table. Only the table differs between one
 * region and the next, so only the table is replaced now.
 *
 * The countdown and today's strip deliberately stay outside this component:
 * they are about *this* mosque, and following the visitor's browsing to another
 * kabupaten would make them quietly wrong.
 */
class PublicPrayerMonth extends Component
{
    /**
     * How far either side of today the visitor may browse.
     *
     * The generator only fills a horizon ahead, so offering a month with nothing
     * in it would render an empty table with no explanation.
     */
    private const MONTH_RANGE = 2;

    /**
     * Kept as query parameters so a month in another kabupaten is still a link
     * someone can paste into a chat thread, and still survives a refresh.
     */
    #[Url(as: 'kota', keep: true)]
    public string $city = '';

    #[Url(as: 'bulan', keep: true)]
    public string $month = '';

    /**
     * The mosque's own kabupaten — the default, and what "back to the mosque's
     * region" returns to.
     */
    public string $homeCity = '';

    /**
     * Livewire assigns matching query parameters onto the typed properties
     * before this runs, so both arrive as strings — empty, not null.
     */
    public function mount(SettingRepositoryInterface $settings, OfficialScheduleClient $official): void
    {
        $this->homeCity = (string) ($settings->get('prayer.official_city_id', '') ?? '');
        $this->city = $this->resolveCity($this->city, $official);
        $this->month = DateHelper::normaliseMonth($this->month);
    }

    public function previousMonth(): void
    {
        $this->stepMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->stepMonth(1);
    }

    /**
     * Back to the kabupaten the mosque is in.
     */
    public function resetCity(): void
    {
        $this->city = $this->homeCity;
    }

    /**
     * Runs when the region select changes.
     *
     * An id that is not in the published list falls back to the mosque's own
     * rather than erroring: this value can arrive from the address bar.
     */
    public function updatedCity(string $value): void
    {
        $this->city = $this->resolveCity($value, app(OfficialScheduleClient::class));
    }

    public function render(
        PrayerScheduleService $schedules,
        OfficialScheduleClient $official,
        SettingService $settings,
    ): View {
        $month = DateHelper::startOfMonthFrom($this->month);
        $isHomeCity = $this->city === $this->homeCity;

        return view('livewire.public-prayer-month', [
            'monthDate' => $month,
            'rows' => $this->rows($schedules, $official, $month, $isHomeCity),
            'cities' => $official->allCities(),
            'cityLabel' => $this->cityLabel($official),
            'isHomeCity' => $isHomeCity,
            'prayers' => PrayerName::cases(),
            'hasPreviousMonth' => $this->boundedMonth($month->subMonth()) !== null,
            'hasNextMonth' => $this->boundedMonth($month->addMonth()) !== null,
            'todayDate' => DateHelper::today()->toDateString(),

            /*
             * Named here rather than inherited.
             *
             * The composer that binds `$mosqueName` is scoped to
             * `layouts.public` and `pages.portal.*`; a Livewire view is
             * neither, and the note that uses it only renders once someone
             * picks another kabupaten — so a missing variable would have waited
             * for the first visitor who did.
             */
            'mosqueName' => $settings->mosqueName(),
        ]);
    }

    /**
     * One month of times, keyed by date.
     *
     * Two shapes reach the table: stored rows for the mosque's own kabupaten,
     * and a live fetch for anywhere else. Both are flattened to
     * `Y-m-d => [prayer => HH:MM]` here rather than in the view, which should
     * not have to know which of the two it is looking at.
     *
     * @return array<string, array<string, string>>
     */
    private function rows(
        PrayerScheduleService $schedules,
        OfficialScheduleClient $official,
        CarbonImmutable $month,
        bool $isHomeCity,
    ): array {
        if (! $isHomeCity) {
            return $official->monthlyTimings($this->city, $month) ?? [];
        }

        return $schedules->forMonth($month)
            ->mapWithKeys(fn ($day): array => [
                DateHelper::toCarbon($day->date)->toDateString() => collect(PrayerName::cases())
                    ->mapWithKeys(fn (PrayerName $prayer): array => [
                        $prayer->value => PublicSchedule::time($day, $prayer),
                    ])
                    ->all(),
            ])
            ->all();
    }

    private function cityLabel(OfficialScheduleClient $official): ?string
    {
        return collect($official->allCities())->firstWhere('id', $this->city)['label'] ?? null;
    }

    /**
     * @return string A published kabupaten id, or the mosque's own.
     */
    private function resolveCity(string $requested, OfficialScheduleClient $official): string
    {
        if ($requested === '') {
            return $this->homeCity;
        }

        $exists = collect($official->allCities())->contains(
            static fn (array $city): bool => $city['id'] === $requested,
        );

        return $exists ? $requested : $this->homeCity;
    }

    /**
     * Moves by whole months, and refuses to leave the browsable window.
     */
    private function stepMonth(int $months): void
    {
        $target = DateHelper::startOfMonthFrom($this->month)->addMonths($months);

        if ($this->boundedMonth($target) !== null) {
            $this->month = $target->format('Y-m');
        }
    }

    /**
     * The month, or null when it falls outside the browsable window — which is
     * what the view uses to disable its previous/next buttons.
     */
    private function boundedMonth(CarbonImmutable $month): ?CarbonImmutable
    {
        $current = DateHelper::today()->startOfMonth();

        $isWithinRange = $month->greaterThanOrEqualTo($current->subMonths(self::MONTH_RANGE))
            && $month->lessThanOrEqualTo($current->addMonths(self::MONTH_RANGE));

        return $isWithinRange ? $month : null;
    }
}
