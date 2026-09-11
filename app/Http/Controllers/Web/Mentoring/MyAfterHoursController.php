<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Mentoring;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Contracts\MentoringGroupRepositoryInterface;
use App\Services\AfterHours\AfterHoursSessionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * After hours, seen from the member's side.
 *
 * The management pages under `sessions.*` and `mentoring-groups.*` are for
 * whoever runs the programme; an employee has no business editing them, but does
 * need to answer two ordinary questions: which halaqah am I in, and when is the
 * next meeting. Without this page they could answer neither — the sidebar showed
 * them nothing at all.
 *
 * Everything here is read-only and scoped to the person asking. No id is
 * accepted from the request, so there is nothing to tamper with: the group comes
 * from their own membership, and the attendance records loaded alongside each
 * session are constrained to them in the repository.
 */
class MyAfterHoursController extends Controller
{
    private const HISTORY_LIMIT = 20;

    private const UPCOMING_LIMIT = 10;

    public function __construct(
        private readonly AfterHoursSessionService $sessions,
        private readonly MentoringGroupRepositoryInterface $groups,
    ) {}

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $group = $this->groups->activeGroupOf($user->id);

        return view('pages.my-after-hours.index', [
            'group' => $group?->loadMissing(['mentor', 'members']),
            'upcoming' => $this->sessions->upcomingForMember($user, self::UPCOMING_LIMIT),
            'past' => $this->sessions->pastForMember($user, self::HISTORY_LIMIT),
            'currentUser' => $user,
        ]);
    }
}
