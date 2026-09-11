<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\Device\PlaybackReportData;
use App\Http\Controllers\Api\V1\Concerns\ResolvesDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePlaybackReportRequest;
use App\Services\Device\PlaybackMonitorService;
use App\Support\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * `POST /api/v1/devices/me/playbacks`
 *
 * Where "adzan sering tidak terdengar" finally becomes measurable: the player
 * reports what it played along with the output level it measured, and the
 * monitor downgrades a nominally successful playback to `silent` when that level
 * never crossed the audible threshold.
 */
class DevicePlaybackController extends Controller
{
    use ResolvesDevice;

    public function __construct(
        private readonly PlaybackMonitorService $monitor,
    ) {}

    public function store(StorePlaybackReportRequest $request): JsonResponse
    {
        $device = $this->device($request);
        $accepted = [];
        $unknown = [];

        foreach ($request->reports() as $payload) {
            $report = PlaybackReportData::fromArray($payload);
            $log = $this->monitor->recordReport($device, $report);

            if ($log === null) {
                $unknown[] = $report->referenceKey;

                continue;
            }

            $accepted[] = [
                'reference_key' => $report->referenceKey,
                'status' => $log->status->value,
                'status_label' => $log->status->label(),
            ];
        }

        return ApiResponse::success(
            ['accepted' => $accepted, 'unknown' => $unknown],
            message: sprintf('%d laporan diterima.', count($accepted)),
            status: Response::HTTP_CREATED,
        );
    }
}
