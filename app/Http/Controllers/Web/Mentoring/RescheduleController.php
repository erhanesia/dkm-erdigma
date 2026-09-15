<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Mentoring;

use App\Http\Controllers\Controller;
use App\Models\AfterHoursSession;
use App\Models\User;
use App\Services\AfterHours\AfterHoursSessionService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\Flash;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Moving a session to another time — "Jadwal Ulang".
 *
 * Its own page rather than the time fields on the edit form, so a move is
 * always recorded as one: the original time is kept, a reason can be given,
 * and a session from a series can take its later meetings along.
 */
class RescheduleController extends Controller
{
    public function __construct(
        private readonly AfterHoursSessionService $sessions,
    ) {}

    public function edit(Request $request, AfterHoursSession $afterHoursSession): View
    {
        $this->authorizeAccess($request, $afterHoursSession);

        return view('pages.sessions.reschedule', [
            'session' => $afterHoursSession->load(['group', 'series']),
        ]);
    }

    public function update(Request $request, AfterHoursSession $afterHoursSession): RedirectResponse
    {
        $this->authorizeAccess($request, $afterHoursSession);

        $validated = $request->validate([
            // Against the app's own clock, which is the one the times are typed in.
            'starts_at' => ['required', 'date', 'after:'.DateHelper::now()->toDateTimeString()],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
            'with_following' => ['nullable', 'boolean'],
        ], [
            'starts_at.after' => 'Jadwal baru harus di waktu yang belum lewat.',
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ], [
            'starts_at' => 'waktu mulai baru',
            'ends_at' => 'waktu selesai baru',
            'reason' => 'alasan',
        ]);

        $moved = $this->sessions->reschedule(
            $afterHoursSession,
            CarbonImmutable::parse($validated['starts_at']),
            CarbonImmutable::parse($validated['ends_at']),
            $validated['reason'] ?? null,
            $request->boolean('with_following'),
        );

        Flash::success($moved > 1
            ? 'Jadwal baru diterapkan ke '.$moved.' kegiatan dalam seri ini.'
            : 'Kegiatan berhasil dijadwal ulang.');

        return redirect()->route('sessions.show', $afterHoursSession);
    }

    private function authorizeAccess(Request $request, AfterHoursSession $session): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdministrator() || $session->group->mentor_id === $user->id) {
            return;
        }

        throw new AccessDeniedHttpException('Anda hanya dapat menjadwal ulang kegiatan halaqah binaan Anda sendiri.');
    }
}
