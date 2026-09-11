<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Services\AfterHours\AfterHoursSessionService;
use App\Support\Helpers\DateHelper;
use Illuminate\View\View;

/**
 * After-hours sessions, publicly.
 *
 * Two limits are deliberate. Only sessions flagged `is_public` appear, and the
 * view is handed nothing beyond the title, topic, time, place and the ustadz
 * leading it — the repository does not even load the memberships, so who is in a
 * mentoring group stays inside the login.
 */
class SessionController extends Controller
{
    private const UPCOMING_LIMIT = 12;

    public function __construct(
        private readonly AfterHoursSessionService $sessions,
    ) {}

    public function index(): View
    {
        $upcoming = $this->sessions->upcomingPublic(self::UPCOMING_LIMIT);

        return view('pages.portal.sessions', [
            // Grouped by day so a week with three sessions reads as a week
            // rather than as three unrelated cards.
            'sessionsByDate' => $upcoming->groupBy(
                static fn ($session): string => $session->starts_at->toDateString(),
            ),
            'total' => $upcoming->count(),
            'serverTime' => DateHelper::now(),
        ]);
    }

    /**
     * One session.
     *
     * `findPublic()` refuses anything the mosque has not agreed to announce, so
     * guessing an id reaches a 404 rather than an internal meeting. Still no
     * attendee list: the repository does not load the memberships at all.
     */
    public function show(int $session): View
    {
        $found = $this->sessions->findPublic($session);

        abort_if($found === null, 404);

        return view('pages.portal.session', [
            'session' => $found,
            'others' => $this->sessions->upcomingPublic(4)
                ->reject(static fn ($other): bool => $other->id === $found->id)
                ->take(3),
        ]);
    }
}
