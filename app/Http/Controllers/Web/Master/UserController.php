<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Master;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\User\UserService;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Account administration.
 *
 * Most rows here are mirrored from the HRIS; this screen owns the two things the
 * HRIS does not know about — who may sign in to DKM, and who is a mentor.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(): View
    {
        return view('pages.users.index', [
            'users' => $this->users->paginate(),
            'roles' => UserRole::options(),
            'departments' => $this->users->departments(),
        ]);
    }

    public function create(): View
    {
        return view('pages.users.create', [
            'roles' => UserRole::options(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->userAttributes(), $request->role());

        Flash::success('Pengguna "'.$user->name.'" berhasil dibuat.');

        return redirect()->route('users.index');
    }

    public function show(User $user): View
    {
        return view('pages.users.show', [
            'user' => $user->load(['roles', 'mentoredGroups', 'groupMemberships.group']),
        ]);
    }

    public function edit(User $user): View
    {
        return view('pages.users.edit', [
            'user' => $user->load('roles'),
            'roles' => UserRole::options(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $attributes = $request->userAttributes();
        $isMentor = (bool) ($attributes['is_mentor'] ?? false);
        unset($attributes['is_mentor']);

        $this->users->update($user, $attributes, $request->role());

        if ($user->refresh()->is_mentor !== $isMentor) {
            $this->users->setMentorFlag($user, $isMentor);
        }

        Flash::success('Data pengguna berhasil diperbarui.');

        return redirect()->route('users.show', $user);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->users->delete($user, $actor);

        Flash::success('Pengguna berhasil dihapus.');

        return redirect()->route('users.index');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'in:is_active,is_mentor'],
            'value' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $value = (bool) $validated['value'];

        if ($validated['field'] === 'is_active') {
            $this->users->toggleActive($user, $value, $actor);

            Flash::success($value ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');

            return back();
        }

        $this->users->setMentorFlag($user, $value);

        Flash::success($value
            ? $user->name.' kini ditandai sebagai mentor dan bisa dipilih untuk membina halaqah.'
            : 'Status mentor '.$user->name.' dicabut.');

        return back();
    }

    /**
     * Admin-issued password reset, for someone who cannot sign in.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->users->resetPassword($user, (string) $validated['password']);

        Flash::success('Password '.$user->name.' berhasil direset.');

        return back();
    }
}
