<?php

declare(strict_types=1);

namespace App\Services\AfterHours;

use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AfterHoursSession;
use App\Models\MentoringGroup;
use App\Models\User;
use App\Repositories\Contracts\AfterHoursSessionRepositoryInterface;
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
            $session = $this->sessions->create([
                ...$attributes,
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

        $this->guardTimeWindow($attributes);

        return DB::transaction(function () use ($session, $attributes): AfterHoursSession {
            $updated = $this->sessions->update($session, $attributes);

            $this->attendances->seedForSession($updated->load('group'));

            return $updated;
        });
    }

    public function delete(AfterHoursSession $session): void
    {
        $this->sessions->delete($session);
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
