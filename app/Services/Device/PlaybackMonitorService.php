<?php

declare(strict_types=1);

namespace App\Services\Device;

use App\DataTransferObjects\Device\PlaybackReportData;
use App\Enums\PlaybackStatus;
use App\Models\Device;
use App\Models\PlaybackLog;
use App\Models\User;
use App\Repositories\Contracts\PlaybackLogRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * The answer to "adzan sering tidak terdengar".
 *
 * Two independent checks run against every scheduled adhan:
 *
 *  1. The player reports what happened, including the measured output level. A
 *     report that says "played" but carries a near-zero level is downgraded to
 *     `silent` — the audio ran, but the room heard nothing.
 *  2. Anything still `pending` well past its scheduled time is marked `missed`,
 *     which catches a device that died or was never switched on.
 */
class PlaybackMonitorService
{
    public function __construct(
        private readonly PlaybackLogRepositoryInterface $playbackLogs,
    ) {}

    /**
     * Record what a device reported about one playback.
     */
    public function recordReport(Device $device, PlaybackReportData $report): ?PlaybackLog
    {
        $log = $this->playbackLogs->findSlot($device->audio_zone_id, $report->referenceKey);

        if ($log === null) {
            Log::warning('Laporan playback untuk slot yang tidak dikenal diabaikan.', [
                'device_id' => $device->id,
                'reference_key' => $report->referenceKey,
            ]);

            return null;
        }

        $threshold = (float) config('dkm.playback.min_audio_level');
        $status = $report->soundedSilent($threshold) ? PlaybackStatus::Silent : $report->status;

        $log->forceFill([
            ...$report->toArray(),
            'status' => $status,
            'device_id' => $device->id,
        ])->save();

        return $log;
    }

    /**
     * Flag every reservation whose deadline passed without a report.
     *
     * @return int Number of slots newly marked as missed.
     */
    public function markOverdueAsMissed(): int
    {
        $threshold = (int) config('dkm.playback.missed_threshold');
        $overdue = $this->playbackLogs->overduePending($threshold);

        foreach ($overdue as $log) {
            $log->forceFill([
                'status' => PlaybackStatus::Missed,
                'failure_reason' => 'Tidak ada laporan dari perangkat dalam '
                    .DateHelper::humanDuration($threshold).' setelah jadwal.',
            ])->save();
        }

        return $overdue->count();
    }

    /**
     * Issues the board still has to look at.
     *
     * @return Collection<int, PlaybackLog>
     */
    public function unresolvedIssues(int $limit = 20): Collection
    {
        return $this->playbackLogs->unresolvedIssues($limit);
    }

    public function acknowledge(PlaybackLog $log, User $user): PlaybackLog
    {
        $log->forceFill([
            'is_acknowledged' => true,
            'acknowledged_by' => $user->id,
            'acknowledged_at' => DateHelper::now(),
        ])->save();

        return $log;
    }

    /**
     * Mark every outstanding issue in one go, for after a fix is deployed.
     */
    public function acknowledgeAll(User $user): int
    {
        $issues = $this->playbackLogs->unresolvedIssues(500);

        foreach ($issues as $issue) {
            $this->acknowledge($issue, $user);
        }

        return $issues->count();
    }

    /**
     * Reliability summary for the monitoring page.
     *
     * @return array{total: int, played: int, silent: int, failed: int, missed: int, success_rate: float}
     */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $breakdown = $this->playbackLogs->dailyBreakdown($from, $to);

        $totals = ['played' => 0, 'silent' => 0, 'failed' => 0, 'missed' => 0];

        foreach ($breakdown as $day) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $day[$key];
            }
        }

        $total = array_sum($totals);

        return [
            ...$totals,
            'total' => $total,
            'success_rate' => $total === 0 ? 100.0 : round($totals['played'] / $total * 100, 1),
        ];
    }

    /**
     * @return array<int, array{date: string, played: int, silent: int, failed: int, missed: int}>
     */
    public function dailyBreakdown(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->playbackLogs->dailyBreakdown($from, $to);
    }

    /**
     * @return array<int, array{zone: string, played: int, problems: int}>
     */
    public function reliabilityByZone(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->playbackLogs->reliabilityByZone($from, $to);
    }

    public function prune(): int
    {
        $cutoff = DateHelper::today()->subDays((int) config('dkm.playback.retention_days'));

        return $this->playbackLogs->pruneOlderThan($cutoff);
    }
}
