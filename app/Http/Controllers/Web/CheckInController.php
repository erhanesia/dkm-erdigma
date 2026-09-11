<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AfterHours\AfterHoursSessionService;
use App\Services\AfterHours\AttendanceService;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Self check-in reached by scanning the session QR code.
 *
 * The token in the URL identifies the session but grants nothing on its own —
 * the person still has to be signed in and be an active member of that halaqah.
 */
class CheckInController extends Controller
{
    public function __construct(
        private readonly AfterHoursSessionService $sessions,
        private readonly AttendanceService $attendances,
    ) {}

    public function show(Request $request, string $token): View
    {
        $session = $this->sessions->findByQrToken($token);

        if ($session === null) {
            throw new NotFoundHttpException('Kode presensi tidak dikenali.');
        }

        /** @var User $user */
        $user = $request->user();

        return view('pages.attendance.check-in', [
            'session' => $session->load('group.mentor'),
            'token' => $token,
            'isOpen' => $session->isCheckInOpen(),
            'existing' => $this->attendances->forSession($session)->firstWhere('user_id', $user->id),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $session = $this->sessions->findByQrToken($token);

        if ($session === null) {
            throw new NotFoundHttpException('Kode presensi tidak dikenali.');
        }

        /** @var User $user */
        $user = $request->user();

        $attendance = $this->attendances->checkIn($session->load('group'), $user);

        Flash::success('Presensi tercatat sebagai "'.$attendance->status->label().'". Barakallahu fiik.');

        return redirect()->route('attendance.check-in', ['token' => $token]);
    }
}
