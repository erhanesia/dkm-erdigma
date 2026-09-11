<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Mentoring;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AfterHoursSession;
use App\Models\User;
use App\Services\AfterHours\AttendanceService;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The mentor's roll call — "daftar hadir yang hadir siapa saja".
 *
 * The list arrives pre-seeded with every group member marked absent, so the
 * mentor only has to change the ones who showed up.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendances,
    ) {}

    public function edit(Request $request, AfterHoursSession $afterHoursSession): View
    {
        $this->authorizeAccess($request, $afterHoursSession);

        return view('pages.attendances.edit', [
            'session' => $afterHoursSession->load('group.mentor'),
            'attendances' => $this->attendances->forSession($afterHoursSession),
            'statuses' => AttendanceStatus::options(),
            'tally' => $this->attendances->tally($afterHoursSession),
        ]);
    }

    public function update(Request $request, AfterHoursSession $afterHoursSession): RedirectResponse
    {
        $this->authorizeAccess($request, $afterHoursSession);

        $validated = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'entries.*.status' => ['required', 'in:'.implode(',', AttendanceStatus::values())],
            'entries.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'entries.required' => 'Tidak ada data presensi yang dikirim.',
        ]);

        /** @var User $recorder */
        $recorder = $request->user();

        $this->attendances->recordBulk(
            $afterHoursSession,
            $this->onlyGroupMembers($afterHoursSession, $validated['entries']),
            $recorder,
        );

        Flash::success('Daftar hadir berhasil disimpan.');

        return redirect()->route('sessions.show', $afterHoursSession);
    }

    /**
     * Drops any row for someone who is not an active member of this halaqah, so
     * a crafted form cannot record attendance for an outsider.
     *
     * @param  array<int, array{user_id: int, status: string, note: string|null}>  $entries
     * @return array<int, array{user_id: int, status: string, note: string|null}>
     */
    private function onlyGroupMembers(AfterHoursSession $session, array $entries): array
    {
        $memberIds = $session->group
            ->memberships()
            ->where('is_active', true)
            ->pluck('user_id')
            ->all();

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => in_array((int) $entry['user_id'], $memberIds, true),
        ));
    }

    private function authorizeAccess(Request $request, AfterHoursSession $session): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdministrator() || $session->group->mentor_id === $user->id) {
            return;
        }

        throw new AccessDeniedHttpException('Anda hanya dapat mengisi presensi halaqah binaan Anda sendiri.');
    }
}
