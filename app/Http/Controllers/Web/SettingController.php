<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\PrayerName;
use App\Http\Controllers\Controller;
use App\Services\Prayer\OfficialScheduleClient;
use App\Services\Prayer\PrayerTimeCalculator;
use App\Services\SettingService;
use App\Support\Helpers\ApiResponse;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\Flash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * Runtime configuration and the audit trail.
 */
class SettingController extends Controller
{
    public function __construct(
        private readonly OfficialScheduleClient $official,
        private readonly SettingService $settings,
        private readonly PrayerTimeCalculator $calculator,
    ) {}

    public function edit(): View
    {
        return view('pages.settings.edit', [
            'values' => $this->settings->all(),
            'groups' => [
                'mosque' => $this->settings->group('mosque'),
                'prayer' => $this->settings->group('prayer'),
                'monitoring' => $this->settings->group('monitoring'),
            ],
            'officialCityLabel' => $this->officialCityLabel(),
            'methods' => $this->calculator->availableMethods(),
            'asrMethods' => $this->calculator->availableAsrMethods(),
            'prayers' => PrayerName::cases(),
            'preview' => $this->calculator->calculateForToday(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mosque.name' => ['required', 'string', 'max:150'],
            'mosque.address' => ['nullable', 'string', 'max:255'],
            'prayer.latitude' => ['required', 'numeric', 'between:-90,90'],
            'prayer.longitude' => ['required', 'numeric', 'between:-180,180'],
            'prayer.elevation' => ['required', 'numeric', 'between:-500,9000'],
            'prayer.calculation_method' => [
                'required', 'in:'.implode(',', array_keys($this->calculator->availableMethods())),
            ],
            'prayer.asr_method' => [
                'required', 'in:'.implode(',', array_keys($this->calculator->availableAsrMethods())),
            ],
            'prayer.adjustment' => ['nullable', 'array'],
            'prayer.adjustment.*' => ['integer', 'between:-30,30'],
            'prayer.official_city_id' => ['nullable', 'string', 'max:20'],
        ], [
            'prayer.latitude.between' => 'Lintang harus di antara -90 dan 90.',
            'prayer.longitude.between' => 'Bujur harus di antara -180 dan 180.',
            'prayer.adjustment.*.between' => 'Koreksi waktu hanya boleh antara -30 sampai 30 menit.',
        ]);

        $this->settings->save($this->flatten($validated));

        Flash::success('Pengaturan disimpan. Jadwal sholat ke depan sudah dihitung ulang.');

        return back();
    }

    /**
     * Today's times for values the form is holding but has not saved.
     *
     * Answers JSON so the preview card can update as the dropdowns change.
     * Read-only: it computes and returns, and writes nothing — so trying a
     * madhab costs nothing and changing your mind costs nothing either.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'elevation' => ['nullable', 'numeric', 'between:-500,9000'],
            'calculation_method' => ['nullable', 'string', 'max:40'],
            'asr_method' => ['nullable', 'string', 'max:40'],
            'adjustment' => ['nullable', 'array'],
            'adjustment.*' => ['nullable', 'integer', 'between:-30,30'],
        ]);

        $calculated = $this->calculator->preview([
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'elevation' => $validated['elevation'] ?? null,
            'calculation_method' => $validated['calculation_method'] ?? null,
            'asr_method' => $validated['asr_method'] ?? null,
            'adjustments' => $validated['adjustment'] ?? null,
        ]);

        /*
         * The preview has to show what saving would actually produce.
         *
         * Since the official Kemenag schedule became the primary source, the
         * calculated figures are only used for days the API cannot supply — so
         * showing them here would promise times that will not be stored. That is
         * worse than showing nothing: it makes the madhab dropdown look like it
         * moves Asr when, on an official day, it does not.
         */
        $official = $this->officialPreview();

        return ApiResponse::success([
            'timings' => $official ?? $calculated,
            'source' => $official === null ? 'calculated' : 'official',
            // Still sent when official wins, so the card can show what the
            // fallback would give — that is what the hisab settings control.
            'fallback' => $official === null ? null : $calculated,
        ]);
    }

    /**
     * The configured kabupaten's name, for showing back on the form.
     */
    private function officialCityLabel(): ?string
    {
        $cityId = (string) ($this->settings->get('prayer.official_city_id', '') ?? '');

        if ($cityId === '') {
            return null;
        }

        return collect($this->official->allCities())
            ->firstWhere('id', $cityId)['label'] ?? null;
    }

    /**
     * Today's officially published times, or null when no city is configured or
     * the API cannot answer.
     *
     * @return array<string, string>|null
     */
    private function officialPreview(): ?array
    {
        $cityId = (string) ($this->settings->get(
            'prayer.official_city_id',
            config('dkm.official_schedule.city_id'),
        ) ?? '');

        if ($cityId === '') {
            return null;
        }

        return $this->official->timingsFor($cityId, DateHelper::today());
    }

    public function activityLog(Request $request): View
    {
        return view('pages.settings.activity-log', [
            'activities' => Activity::query()
                ->with('causer')
                ->when(
                    $request->filled('log_name'),
                    fn ($query) => $query->where('log_name', $request->string('log_name')->toString()),
                )
                ->latest()
                ->paginate((int) config('dkm.per_page'))
                ->withQueryString(),
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->all(),
        ]);
    }

    /**
     * Turns the nested form payload into the flat `key => value` shape the
     * settings table stores, e.g. `prayer.adjustment.fajr`.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string|int|float|bool|null>
     */
    private function flatten(array $validated): array
    {
        $flat = [];

        foreach ($validated as $group => $entries) {
            foreach ((array) $entries as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $childKey => $childValue) {
                        $flat[$group.'.'.$key.'.'.$childKey] = $childValue;
                    }

                    continue;
                }

                $flat[$group.'.'.$key] = $value;
            }
        }

        return $flat;
    }
}
