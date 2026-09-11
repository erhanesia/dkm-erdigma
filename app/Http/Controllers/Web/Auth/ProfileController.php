<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use App\Services\AfterHours\MentoringGroupService;
use App\Services\Friday\PrayerDutyService;
use App\Services\User\UserService;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly MentoringGroupService $groups,
        private readonly PrayerDutyService $duties,
    ) {}

    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('pages.profile.edit', [
            'user' => $user,
            'group' => $this->groups->activeGroupOf($user),
            'upcomingDuties' => $this->duties->upcomingForUser($user),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->users->updateOwnProfile($user, $request->validated());

        Flash::success('Profil berhasil diperbarui.');

        return back();
    }
}
