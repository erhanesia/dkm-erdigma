<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\SessionStatus;
use App\Models\AfterHoursSession;
use App\Repositories\Contracts\AfterHoursSessionRepositoryInterface;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @extends BaseRepository<AfterHoursSession>
 */
class AfterHoursSessionRepository extends BaseRepository implements AfterHoursSessionRepositoryInterface
{
    /**
     * What the public may see of a session: announced, under way, or held.
     * A cancelled session is no longer an announcement.
     *
     * Shared by the detail page and the calendar, so nothing the calendar shows
     * can lead to a page that refuses to open.
     *
     * @var array<int, string>
     */
    private const PUBLIC_STATUSES = [
        SessionStatus::Scheduled->value,
        SessionStatus::Ongoing->value,
        SessionStatus::Completed->value,
    ];

    protected string $defaultOrderColumn = 'starts_at';

    protected string $defaultOrderDirection = 'desc';

    protected function model(): string
    {
        return AfterHoursSession::class;
    }

    public function paginateFiltered(?array $groupIds = null): LengthAwarePaginator
    {
        return QueryBuilder::for(AfterHoursSession::class)
            ->with(['group.mentor', 'mentor'])
            ->withCount([
                'attendances',
                'attendances as attending_count' => static fn (Builder $query) => $query->attending(),
            ])
            ->when($groupIds !== null, static fn ($query) => $query->whereIn('mentoring_group_id', $groupIds))
            ->allowedFilters(
                AllowedFilter::callback('search', static function (Builder $query, mixed $value): void {
                    $term = '%'.$value.'%';

                    $query->where(function (Builder $builder) use ($term): void {
                        $builder->where('topic', 'like', $term)->orWhere('description', 'like', $term);
                    });
                }),
                AllowedFilter::exact('mentoring_group_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('date_from', static function (Builder $query, mixed $value): void {
                    $query->where('starts_at', '>=', DateHelper::toCarbon($value)->startOfDay());
                }),
                AllowedFilter::callback('date_to', static function (Builder $query, mixed $value): void {
                    $query->where('starts_at', '<=', DateHelper::toCarbon($value)->endOfDay());
                }), )
            ->allowedSorts('starts_at', 'topic', 'status')
            ->defaultSort('-starts_at')
            ->paginate((int) config('dkm.per_page'))
            ->withQueryString();
    }

    public function findByQrToken(string $token): ?AfterHoursSession
    {
        return $this->query()->with('group')->where('qr_token', $token)->first();
    }

    public function upcoming(int $limit = 5, ?array $groupIds = null): Collection
    {
        return $this->query()
            ->with(['group', 'mentor'])
            ->when($groupIds !== null, static fn (Builder $query) => $query->whereIn('mentoring_group_id', $groupIds))
            ->upcoming()
            ->limit($limit)
            ->get();
    }

    /**
     * Sessions already held for the groups this person belongs to, each carrying
     * that person's own attendance record and nothing about anyone else's.
     *
     * `attendances` is constrained to the caller rather than loaded whole: a
     * member is entitled to see whether *they* turned up, not to read the
     * register for their colleagues.
     *
     * @return Collection<int, AfterHoursSession>
     */
    public function pastForMember(int $userId, int $limit = 20): Collection
    {
        return $this->query()
            ->with([
                'group.mentor',
                'attendances' => static fn ($query) => $query->where('user_id', $userId),
            ])
            ->whereHas('group.memberships', static function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId)->where('is_active', true);
            })
            ->where('starts_at', '<', DateHelper::now())
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();
    }

    public function upcomingForMember(int $userId, int $limit = 5): Collection
    {
        return $this->query()
            ->with(['group.mentor'])
            ->whereHas('group.memberships', static function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId)->where('is_active', true);
            })
            ->upcoming()
            ->limit($limit)
            ->get();
    }

    /**
     * The public listing.
     *
     * Only `group.mentor` and `mentor` are eager-loaded — deliberately not the
     * memberships or attendances, so a template mistake on the public page
     * cannot put an employee list in front of a stranger.
     */
    /**
     * One session, but only if the mosque has agreed to announce it.
     *
     * The `is_public` check belongs here rather than in the controller: a
     * session hidden from the listing must also be unreachable by guessing its
     * id, and putting the rule beside the query is what guarantees both callers
     * obey it.
     */
    public function findPublic(int $id): ?AfterHoursSession
    {
        return $this->query()
            /*
             * `group.members` carries the roster the public page now shows, but
             * only three columns of it. The relation loads whole User models by
             * default — email, phone, employee number — and none of that belongs
             * on a page anyone can open. Narrowing it here means a template
             * mistake cannot print what was never fetched.
             *
             * `attendances` stays absent: who belongs to a halaqah is an
             * announcement, who failed to turn up is not.
             */
            ->with([
                'group.members' => static fn ($query) => $query->select([
                    'users.id',
                    'users.name',
                    'users.position_name',
                    'users.team_name',
                ]),
                'group.mentor',
                'mentor',
            ])
            ->where('is_public', true)
            ->whereIn('status', self::PUBLIC_STATUSES)
            ->find($id);
    }

    public function upcomingPublic(int $limit = 5): Collection
    {
        return $this->query()
            ->with(['group', 'mentor'])
            ->where('is_public', true)
            ->whereIn('status', [SessionStatus::Scheduled->value, SessionStatus::Ongoing->value])
            ->upcoming()
            ->limit($limit)
            ->get();
    }

    /**
     * Like the listing, only `group` and `mentor` are loaded — never the
     * memberships — so the calendar cannot put a roster in front of a stranger.
     */
    public function publicBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->query()
            ->with(['group', 'mentor'])
            ->where('is_public', true)
            ->whereIn('status', self::PUBLIC_STATUSES)
            ->between($from, $to)
            ->orderBy('starts_at')
            ->get();
    }

    public function firstPublicStart(): ?CarbonImmutable
    {
        $startsAt = $this->query()
            ->where('is_public', true)
            ->whereIn('status', self::PUBLIC_STATUSES)
            ->min('starts_at');

        return $startsAt === null ? null : CarbonImmutable::parse((string) $startsAt);
    }

    public function between(CarbonImmutable $from, CarbonImmutable $to, ?array $groupIds = null): Collection
    {
        return $this->query()
            ->with(['group.mentor'])
            ->when($groupIds !== null, static fn (Builder $query) => $query->whereIn('mentoring_group_id', $groupIds))
            ->between($from, $to)
            ->orderBy('starts_at')
            ->get();
    }

    public function endedButOpen(): Collection
    {
        return $this->query()
            ->whereIn('status', [SessionStatus::Scheduled, SessionStatus::Ongoing])
            ->where('ends_at', '<', DateHelper::now())
            ->get();
    }

    public function datesTakenByGroup(int $groupId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->query()
            ->where('mentoring_group_id', $groupId)
            ->where('status', '!=', SessionStatus::Cancelled->value)
            ->whereBetween('starts_at', [$from->startOfDay()->toDateTimeString(), $to->endOfDay()->toDateTimeString()])
            ->pluck('starts_at')
            ->map(static fn ($startsAt): string => CarbonImmutable::parse((string) $startsAt)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    public function movableFromInSeries(AfterHoursSession $session): Collection
    {
        return $this->query()
            ->where('series_id', $session->series_id)
            ->where('starts_at', '>=', $session->starts_at)
            ->where('starts_at', '>', DateHelper::now())
            ->where('status', SessionStatus::Scheduled->value)
            ->whereDoesntHave('attendances', static fn (Builder $query) => $query->attending())
            ->orderBy('starts_at')
            ->get();
    }

    public function countInMonth(CarbonImmutable $month): int
    {
        return $this->query()
            ->whereBetween('starts_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->count();
    }
}
