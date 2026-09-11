<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Mentoring;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Session\StoreSessionRequest;
use App\Models\AfterHoursSession;
use App\Models\User;
use App\Services\AfterHours\AfterHoursSessionService;
use App\Services\AfterHours\AttendanceService;
use App\Services\AfterHours\MentoringGroupService;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * After-hours mentoring sessions — "kapan jadwalnya".
 */
class SessionController extends Controller
{
    public function __construct(
        private readonly AfterHoursSessionService $sessions,
        private readonly MentoringGroupService $groups,
        private readonly AttendanceService $attendances,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('pages.sessions.index', [
            'sessions' => $this->sessions->paginate($this->scopedGroupIds($user)),
            'groups' => $this->groups->options($user->isAdministrator() ? null : $user),
            'statuses' => SessionStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('pages.sessions.create', [
            'groups' => $this->groups->options($user->isAdministrator() ? null : $user),
            'statuses' => SessionStatus::options(),
        ]);
    }

    public function store(StoreSessionRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $session = $this->sessions->create($request->validated(), $actor);

        Flash::success('Kegiatan "'.$session->topic.'" berhasil dijadwalkan. '
            .'Daftar hadir sudah disiapkan untuk semua anggota halaqah.');

        return redirect()->route('sessions.show', $session);
    }

    public function show(Request $request, AfterHoursSession $afterHoursSession): View
    {
        $this->authorizeAccess($request, $afterHoursSession);

        $afterHoursSession->load(['group.mentor', 'mentor']);

        return view('pages.sessions.show', [
            'session' => $afterHoursSession,
            'attendances' => $this->attendances->forSession($afterHoursSession),
            'tally' => $this->attendances->tally($afterHoursSession),
            'isCheckInOpen' => $afterHoursSession->isCheckInOpen(),
        ]);
    }

    public function edit(Request $request, AfterHoursSession $afterHoursSession): View
    {
        $this->authorizeAccess($request, $afterHoursSession);

        /** @var User $user */
        $user = $request->user();

        return view('pages.sessions.edit', [
            'session' => $afterHoursSession,
            'groups' => $this->groups->options($user->isAdministrator() ? null : $user),
            'statuses' => SessionStatus::options(),
        ]);
    }

    public function update(StoreSessionRequest $request, AfterHoursSession $afterHoursSession): RedirectResponse
    {
        $this->authorizeAccess($request, $afterHoursSession);

        $this->sessions->update($afterHoursSession, $request->validated());

        Flash::success('Kegiatan berhasil diperbarui.');

        return redirect()->route('sessions.show', $afterHoursSession);
    }

    public function destroy(Request $request, AfterHoursSession $afterHoursSession): RedirectResponse
    {
        $this->authorizeAccess($request, $afterHoursSession);

        $this->sessions->delete($afterHoursSession);

        Flash::success('Kegiatan berhasil dihapus.');

        return redirect()->route('sessions.index');
    }

    /**
     * The QR poster members scan to check themselves in.
     */
    public function qrCode(Request $request, AfterHoursSession $afterHoursSession): View
    {
        $this->authorizeAccess($request, $afterHoursSession);

        return view('pages.sessions.qr', [
            'session' => $afterHoursSession->load('group'),
            'qrSvg' => $this->sessions->qrCodeSvg($afterHoursSession),
            'checkInUrl' => $this->sessions->checkInUrl($afterHoursSession),
            'isCheckInOpen' => $afterHoursSession->isCheckInOpen(),
        ]);
    }

    /**
     * Regenerates the QR secret, invalidating any photo of the previous code.
     */
    public function rotateQr(Request $request, AfterHoursSession $afterHoursSession): RedirectResponse
    {
        $this->authorizeAccess($request, $afterHoursSession);

        $this->sessions->rotateQrToken($afterHoursSession);

        Flash::success('Kode QR diperbarui. Kode lama sudah tidak berlaku.');

        return redirect()->route('sessions.qr', $afterHoursSession);
    }

    /**
     * Group ids the signed-in user may see, or null for administrators.
     *
     * @return array<int, int>|null
     */
    private function scopedGroupIds(User $user): ?array
    {
        return $user->isAdministrator() ? null : $this->sessions->accessibleGroupIds($user);
    }

    private function authorizeAccess(Request $request, AfterHoursSession $session): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdministrator() || $session->group->mentor_id === $user->id) {
            return;
        }

        throw new AccessDeniedHttpException('Anda hanya dapat mengakses kegiatan halaqah binaan Anda sendiri.');
    }
}
