<?php

declare(strict_types=1);

namespace App\Services\Audio;

use App\Exceptions\BusinessRuleException;
use App\Models\MurottalSchedule;
use App\Models\User;
use App\Repositories\Contracts\AudioZoneRepositoryInterface;
use App\Repositories\Contracts\MurottalScheduleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Tilawah windows per room — the module that makes the CEO room's setting an
 * explicit, visible choice instead of an accident of the local script.
 */
class MurottalScheduleService
{
    public function __construct(
        private readonly MurottalScheduleRepositoryInterface $schedules,
        private readonly AudioZoneRepositoryInterface $zones,
        private readonly AudioZoneService $zoneService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, MurottalSchedule>
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->schedules->paginateFiltered();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?User $actor = null): MurottalSchedule
    {
        $this->guardAgainstOverlap($attributes);

        $schedule = $this->schedules->create($attributes);

        $this->notifyZone($schedule, $actor);

        return $schedule;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(MurottalSchedule $schedule, array $attributes, ?User $actor = null): MurottalSchedule
    {
        $this->guardAgainstOverlap($attributes, $schedule->id);

        $updated = $this->schedules->update($schedule, $attributes);

        $this->notifyZone($updated, $actor);

        return $updated;
    }

    public function delete(MurottalSchedule $schedule, ?User $actor = null): void
    {
        $this->notifyZone($schedule, $actor);

        $this->schedules->delete($schedule);
    }

    public function toggleActive(MurottalSchedule $schedule, bool $active, ?User $actor = null): MurottalSchedule
    {
        $updated = $this->schedules->update($schedule, ['is_active' => $active]);

        $this->notifyZone($updated, $actor);

        return $updated;
    }

    /**
     * Two overlapping windows in the same room would fight over the speakers.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function guardAgainstOverlap(array $attributes, ?int $exceptId = null): void
    {
        $startTime = (string) $attributes['start_time'];
        $endTime = (string) $attributes['end_time'];

        if ($endTime <= $startTime) {
            throw new BusinessRuleException('Jam selesai harus lebih besar daripada jam mulai.');
        }

        $overlaps = $this->schedules->hasOverlap(
            (int) $attributes['audio_zone_id'],
            $startTime,
            $endTime,
            array_map('intval', (array) ($attributes['days_of_week'] ?? [])),
            $exceptId,
        );

        if ($overlaps) {
            throw new BusinessRuleException(
                'Jadwal ini bertabrakan dengan jadwal murottal lain di zona yang sama pada hari yang sama.',
            );
        }
    }

    private function notifyZone(MurottalSchedule $schedule, ?User $actor): void
    {
        $zone = $schedule->zone ?? $this->zones->find($schedule->audio_zone_id);

        if ($zone !== null) {
            $this->zoneService->pushPlanReload($zone, $actor);
        }
    }
}
