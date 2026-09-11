<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Prayer\OfficialScheduleClient;
use App\Services\Prayer\PrayerCalibrationService;
use App\Services\Prayer\ReverseGeocodeClient;
use App\Support\Helpers\ApiResponse;
use App\Support\Helpers\Flash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Aligns the locally calculated schedule with the published Kemenag times.
 *
 * The search and preview steps answer over JSON so the settings page can show
 * the comparison before anything is committed — nobody should have to save and
 * then check whether the numbers moved the right way.
 */
class PrayerCalibrationController extends Controller
{
    public function __construct(
        private readonly PrayerCalibrationService $calibration,
        private readonly ReverseGeocodeClient $geocoder,
        private readonly OfficialScheduleClient $official,
    ) {}

    /**
     * Type-ahead over the official city list.
     */
    public function searchCities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:60'],
        ]);

        return ApiResponse::success(
            $this->calibration->searchCities((string) $validated['q']),
        );
    }

    /**
     * Show the difference per prayer without saving it.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['required', 'string', 'max:20'],
        ]);

        return ApiResponse::success(
            $this->calibration->preview((string) $validated['city_id']),
        );
    }

    /**
     * Store the measured offsets and regenerate the upcoming schedule.
     */
    public function apply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'city_id' => ['required', 'string', 'max:20'],
        ]);

        $result = $this->calibration->apply((string) $validated['city_id']);

        Flash::success(sprintf(
            'Jadwal dikalibrasi dengan %s. %d waktu sholat disesuaikan, %d hari dihitung ulang.',
            $result['city'] ?? 'jadwal resmi',
            $result['applied'],
            $result['days'],
        ));

        return back();
    }

    /**
     * Describes a coordinate pair in words, so the Settings form can fill the
     * address in after the location is detected.
     */
    public function describeLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        /*
         * The coordinates alone are no longer enough.
         *
         * Since the published Kemenag schedule became the primary source, the
         * times come from a kabupaten rather than from a point — so detecting a
         * location has to identify the kabupaten too, or the schedule would
         * still belong to wherever the setting was last pointed.
         */
        $city = $this->official->matchCity(
            $this->geocoder->placeNames($latitude, $longitude),
        );

        return ApiResponse::success([
            'address' => $this->geocoder->describe($latitude, $longitude),
            'city' => $city,
        ]);
    }
}
