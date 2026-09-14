<?php

declare(strict_types=1);

namespace App\Services\AfterHours;

use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AfterHoursSession;
use App\Models\MentoringGroup;
use App\Models\User;
use App\Repositories\Contracts\AfterHoursSessionRepositoryInterface;
use App\Repositories\Contracts\AfterHoursSessionSeriesRepositoryInterface;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\MentoringGroupRepositoryInterface;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\TokenHelper;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * After-hours mentoring sessions: when they happen and who is expected.
 */
class AfterHoursSessionService
{
    public function __construct(
        private readonly AfterHoursSessionRepositoryInterface $sessions,
        private readonly AttendanceRepositoryInterface $attendances,
        private readonly MentoringGroupRepositoryInterface $groups,
        private readonly AfterHoursSessionSeriesRepositoryInterface $series,
        private readonly LocationService $locations,
    ) {}

    /**
     * @param  array<int, int>|null  $groupIds  Scopes a mentor to their own halaqah.
     * @return LengthAwarePaginator<int, AfterHoursSession>
     */
    public function paginate(?array $groupIds = null): LengthAwarePaginator
    {
        return $this->sessions->paginateFiltered($groupIds);
    }

    /**
     * Creating a session immediately seeds an `absent` row per member, so the
     * mentor opens a ready checklist instead of an empty page.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): AfterHoursSession
    {
        $this->guardTimeWindow($attributes);

        $group = $this->groups->findOrFail((int) $attributes['mentoring_group_id']);

        return DB::transaction(function () use ($attributes, $group, $actor): AfterHoursSession {
            $place = $this->locations->resolve($attributes['location'] ?? null);

            $session = $this->sessions->create([
                ...$attributes,
                'location' => $place['name'],
                'location_id' => $place['id'],
                'mentor_id' => $attributes['mentor_id'] ?? $group->mentor_id,
                'qr_token' => TokenHelper::generateSessionToken(),
                'created_by' => $actor->id,
            ]);

            $session->setRelation('group', $group);
            $this->attendances->seedForSession($session);

            return $session;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(AfterHoursSession $session, array $attributes): AfterHoursSession
    {
        if (! $session->status->isEditable()) {
            throw new BusinessRuleException('Sesi yang sudah selesai tidak dapat diubah.');
        }

        // Only a caller that sends a time has one to check. The edit form no
        // longer does: moving a session is what reschedule() is for.
        if (isset($attributes['starts_at'], $attributes['ends_at'])) {
            $this->guardTimeWindow($attributes);
        }

        return DB::transaction(function () use ($session, $attributes): AfterHoursSession {
            if (array_key_exists('location', $attributes)) {
                $place = $this->locations->resolve($attributes['location']);
                $attributes = [...$attributes, 'location' => $place['name'], 'location_id' => $place['id']];
            }

            $updated = $this->sessions->update($session, $attributes);

            $this->attendances->seedForSession($updated->load('group'));

            return $updated;
        });
    }

    public function delete(AfterHoursSession $session): void
    {
        $this->sessions->delete($session);
    }

    /**
     * Moves a session to a new time, remembering the time it was first set for
     * so every screen can say it moved instead of quietly showing a new date.
     *
     * With `$withFollowing` on a session from a series, the same shift carries
     * to every later session of that series that can still move — scheduled,
     * not yet started, and with nobody marked present. Past and completed
     * meetings are never touched, so a series' history stays what happened.
     *
     * Attendance rows and the QR token stay as they are; the check-in window
     * follows the new time by itself.
     *
     * @return int How many sessions moved.
     */
    public function reschedule(
        AfterHoursSession $session,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?string $reason = null,
        bool $withFollowing = false,
    ): int {
        if ($session->status !== SessionStatus::Scheduled) {
            throw new BusinessRuleException('Hanya kegiatan berstatus Terjadwal yang bisa dijadwal ulang.');
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new BusinessRuleException('Waktu selesai harus setelah waktu mulai.');
        }

        $shift = $startsAt->getTimestamp() - $session->starts_at->getTimestamp();
        $duration = $endsAt->getTimestamp() - $startsAt->getTimestamp();
        $moveSeries = $withFollowing && $session->series_id !== null;

        $targets = collect([$session]);

        if ($moveSeries) {
            $targets = $targets->concat(
                $this->sessions->movableFromInSeries($session)
                    ->reject(static fn (AfterHoursSession $later): bool => $later->is($session)),
            );
        }

        DB::transaction(function () use ($targets, $shift, $duration, $reason, $moveSeries, $session, $startsAt, $endsAt): void {
            foreach ($targets as $target) {
                $from = $target->starts_at->toImmutable();
                $newStart = $from->addSeconds($shift);

                $this->sessions->update($target, [
                    'starts_at' => $newStart->toDateTimeString(),
                    'ends_at' => $newStart->addSeconds($duration)->toDateTimeString(),
                    // Only the first move is remembered: moved twice, a session
                    // still shows the time people originally planned around.
                    'rescheduled_from' => $target->rescheduled_from?->toDateTimeString() ?? $from->toDateTimeString(),
                    'reschedule_reason' => $reason,
                ]);
            }

            if ($moveSeries) {
                $this->series->update($session->loadMissing('series')->series, [
                    'weekday' => $startsAt->dayOfWeekIso,
                    'start_time' => $startsAt->format('H:i:s'),
                    'end_time' => $endsAt->format('H:i:s'),
                ]);
            }
        });

        return $targets->count();
    }

    public function updateStatus(AfterHoursSession $session, SessionStatus $status): AfterHoursSession
    {
        return $this->sessions->update($session, ['status' => $status->value]);
    }

    /**
     * Regenerate the QR secret, invalidating any photo of the old code.
     */
    public function rotateQrToken(AfterHoursSession $session): AfterHoursSession
    {
        return $this->sessions->update($session, ['qr_token' => TokenHelper::generateSessionToken()]);
    }

    /**
     * Inline SVG for the check-in QR code.
     */
    public function qrCodeSvg(AfterHoursSession $session, int $size = 260): string
    {
        return (string) QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($this->checkInUrl($session));
    }

    public function checkInUrl(AfterHoursSession $session): string
    {
        return route('attendance.check-in', ['token' => $session->qr_token]);
    }

    public function findByQrToken(string $token): ?AfterHoursSession
    {
        return $this->sessions->findByQrToken($token);
    }

    /**
     * @param  array<int, int>|null  $groupIds
     * @return Collection<int, AfterHoursSession>
     */
    public function upcoming(int $limit = 5, ?array $groupIds = null): Collection
    {
        return $this->sessions->upcoming($limit, $groupIds);
    }

    /**
     * @return Collection<int, AfterHoursSession>
     */
    /**
     * Upcoming sessions for the public site.
     *
     * @return Collection<int, AfterHoursSession>
     */
    public function findPublic(int $id): ?AfterHoursSession
    {
        return $this->sessions->findPublic($id);
    }

    public function upcomingPublic(int $limit = 5): Collection
    {
        return $this->sessions->upcomingPublic($limit);
    }

    /**
     * @return Collection<int, AfterHoursSession>
     */
    public function pastForMember(User $user, int $limit = 20): Collection
    {
        return $this->sessions->pastForMember($user->id, $limit);
    }

    public function upcomingForMember(User $user, int $limit = 5): Collection
    {
        return $this->sessions->upcomingForMember($user->id, $limit);
    }

    /**
     * @return Collection<int, AfterHoursSession>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to, ?array $groupIds = null): Collection
    {
        return $this->sessions->between($from, $to, $groupIds);
    }

    /**
     * Close sessions whose end time has passed — run by the scheduler so the
     * attendance list stops accepting late check-ins on its own.
     */
    public function closeFinishedSessions(): int
    {
        $stale = $this->sessions->endedButOpen();

        foreach ($stale as $session) {
            $this->sessions->update($session, ['status' => SessionStatus::Completed->value]);
        }

        return $stale->count();
    }

    /**
     * Group ids a mentor is allowed to see, used to scope every listing.
     *
     * @return array<int, int>
     */
    public function accessibleGroupIds(User $user): array
    {
        return $this->groups->activeForMentor($user->id)->pluck('id')->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function guardTimeWindow(array $attributes): void
    {
        $startsAt = DateHelper::toCarbon($attributes['starts_at']);
        $endsAt = DateHelper::toCarbon($attributes['ends_at']);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new BusinessRuleException('Waktu selesai harus setelah waktu mulai.');
        }
    }

    public function groupForMentor(User $mentor): ?MentoringGroup
    {
        return $this->groups->activeForMentor($mentor->id)->first();
    }
}
