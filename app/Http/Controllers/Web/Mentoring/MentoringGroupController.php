<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Mentoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\MentoringGroup\StoreMentoringGroupRequest;
use App\Models\MentoringGroup;
use App\Models\User;
use App\Services\AfterHours\MentoringGroupService;
use App\Services\User\UserService;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Halaqah administration — "mentornya memegang karyawan siapa saja".
 *
 * A mentor sees only their own groups; administrators see all of them.
 */
class MentoringGroupController extends Controller
{
    public function __construct(
        private readonly MentoringGroupService $groups,
        private readonly UserService $users,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('pages.mentoring-groups.index', [
            'groups' => $this->groups->paginate($user->isAdministrator() ? null : $user),
            'mentors' => $this->users->mentorOptions(),
            'canManage' => $user->isAdministrator(),
        ]);
    }

    public function create(): View
    {
        return view('pages.mentoring-groups.create', [
            'mentors' => $this->users->mentorOptions(),
            'employees' => $this->groups->assignableEmployees(),
            'defaultCapacity' => (int) config('dkm.mentoring.default_group_capacity'),
        ]);
    }

    public function store(StoreMentoringGroupRequest $request): RedirectResponse
    {
        $group = $this->groups->create($request->groupAttributes(), $request->memberIds());

        Flash::success('Halaqah "'.$group->name.'" berhasil dibuat.');

        return redirect()->route('mentoring-groups.show', $group);
    }

    public function show(Request $request, MentoringGroup $mentoringGroup): View
    {
        $this->authorizeAccess($request, $mentoringGroup);

        $mentoringGroup->load(['mentor', 'members']);

        return view('pages.mentoring-groups.show', [
            'group' => $mentoringGroup,
            'members' => $mentoringGroup->members,
            'remainingSlots' => $mentoringGroup->remainingSlots(),
            'sessions' => $mentoringGroup->sessions()->latest('starts_at')->limit(10)->get(),
        ]);
    }

    public function edit(Request $request, MentoringGroup $mentoringGroup): View
    {
        $this->authorizeAccess($request, $mentoringGroup);

        return view('pages.mentoring-groups.edit', [
            'group' => $mentoringGroup->load('members'),
            'mentors' => $this->users->mentorOptions(),
            'employees' => $this->groups->assignableEmployees($mentoringGroup),
            'selectedMemberIds' => $mentoringGroup->members->pluck('id')->all(),
        ]);
    }

    public function update(StoreMentoringGroupRequest $request, MentoringGroup $mentoringGroup): RedirectResponse
    {
        $this->authorizeAccess($request, $mentoringGroup);

        $this->groups->update(
            $mentoringGroup,
            $request->groupAttributes(),
            $request->has('member_ids') ? $request->memberIds() : null,
        );

        Flash::success('Halaqah berhasil diperbarui.');

        return redirect()->route('mentoring-groups.show', $mentoringGroup);
    }

    public function destroy(MentoringGroup $mentoringGroup): RedirectResponse
    {
        $this->groups->delete($mentoringGroup);

        Flash::success('Halaqah berhasil dihapus.');

        return redirect()->route('mentoring-groups.index');
    }

    /**
     * A mentor may only reach their own halaqah.
     */
    private function authorizeAccess(Request $request, MentoringGroup $group): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdministrator() || $group->mentor_id === $user->id) {
            return;
        }

        throw new AccessDeniedHttpException('Anda hanya dapat mengakses halaqah binaan Anda sendiri.');
    }
}
