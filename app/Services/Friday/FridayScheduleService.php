<?php

declare(strict_types=1);

namespace App\Services\Friday;

use App\Enums\DutyStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\FridaySchedule;
use App\Models\User;
use App\Repositories\Contracts\FridayScheduleRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Friday sermon roster: khatib, imam, muezzin, and the topic.
 */
class FridayScheduleService
{
    public function __construct(
        private readonly FridayScheduleRepositoryInterface $schedules,
    ) {}

    /**
     * @return LengthAwarePaginator<int, FridaySchedule>
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->schedules->paginateFiltered();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): FridaySchedule
    {
        $this->guardIsFriday($attributes['date']);
        $this->guardNoDuplicate($attributes['date']);

        return $this->schedules->create([...$attributes, 'created_by' => $actor->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(FridaySchedule $schedule, array $attributes): FridaySchedule
    {
        $this->guardIsFriday($attributes['date']);
        $this->guardNoDuplicate($attributes['date'], $schedule->id);

        return $this->schedules->update($schedule, $attributes);
    }

    public function delete(FridaySchedule $schedule): void
    {
        $this->schedules->delete($schedule);
    }

    public function updateStatus(FridaySchedule $schedule, DutyStatus $status): FridaySchedule
    {
        return $this->schedules->update($schedule, ['status' => $status->value]);
    }

    /**
     * One Friday by its date.
     *
     * The date is the natural key here — `date` is unique on this table, and a
     * link built from it says what it points at.
     */
    public function forDate(CarbonImmutable|string $date): ?FridaySchedule
    {
        return $this->schedules->forDate($date);
    }

    public function nextUpcoming(): ?FridaySchedule
    {
        return $this->schedules->nextUpcoming();
    }

    /**
     * @return Collection<int, FridaySchedule>
     */
    public function upcoming(int $limit = 4): Collection
    {
        return $this->schedules->upcoming($limit);
    }

    /**
     * @return Collection<int, FridaySchedule>
     */
    public function forMonth(CarbonImmutable $month): Collection
    {
        return $this->schedules->inMonth($month);
    }

    /**
     * Fridays in the next `$weeks` weeks that still have nobody assigned.
     *
     * @return array<int, string>
     */
    public function unscheduledFridays(int $weeks = 4): array
    {
        $from = DateHelper::today();

        return $this->schedules->unscheduledFridays($from, $from->addWeeks($weeks));
    }

    private function guardIsFriday(mixed $date): void
    {
        if (! DateHelper::toCarbon($date)->isFriday()) {
            throw new BusinessRuleException("Tanggal yang dipilih bukan hari Jum'at.");
        }
    }

    private function guardNoDuplicate(mixed $date, ?int $exceptId = null): void
    {
        $existing = $this->schedules->forDate($date);

        if ($existing !== null && $existing->id !== $exceptId) {
            throw new BusinessRuleException(
                'Sudah ada jadwal untuk tanggal '.DateHelper::formatDate($date).'.',
            );
        }
    }
}
