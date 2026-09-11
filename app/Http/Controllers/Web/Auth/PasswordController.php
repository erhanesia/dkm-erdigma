<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use App\Services\User\UserService;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function __invoke(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->users->changeOwnPassword(
            $user,
            (string) $request->validated('current_password'),
            (string) $request->validated('password'),
        );

        Flash::success('Password berhasil diubah.');

        return back();
    }
}
