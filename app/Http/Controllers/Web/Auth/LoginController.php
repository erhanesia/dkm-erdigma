<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\User\UserService;
use App\Support\Helpers\Flash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function create(): View
    {
        $accounts = $this->demoAccounts();

        return view('pages.auth.login', [
            // One per role up front — that is what reaches every variant of the
            // dashboard. The rest are there but folded away, because a wall of
            // twenty-odd buttons is a list nobody reads.
            'demoAccounts' => $accounts['featured'],
            'demoOthers' => $accounts['others'],
        ]);
    }

    /**
     * Sign-in shortcuts shown below the form outside production.
     *
     * Read from the database rather than listed in the Blade. A hard-coded list
     * goes stale the moment a seeder changes — which is exactly what happened
     * when the employee accounts were added and the login page kept offering the
     * same four.
     *
     * @return array{featured: Collection<int, User>, others: Collection<int, User>}
     */
    private function demoAccounts(): array
    {
        if (app()->isProduction()) {
            return ['featured' => collect(), 'others' => collect()];
        }

        $featured = collect(UserRole::cases())
            ->map(static fn (UserRole $role): ?User => User::query()
                ->whereHas('roles', static fn (Builder $query) => $query->where('name', $role->value))
                ->orderBy('id')
                ->first())
            ->filter()
            ->values();

        $others = User::query()
            ->whereNotIn('id', $featured->pluck('id'))
            ->whereNotNull('team_name')
            ->orderByDesc('is_mentor')
            ->orderBy('team_name')
            ->get();

        return ['featured' => $featured, 'others' => $others];
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->credentials(), $request->boolean('remember'))) {
            /*
             * One message for both a wrong password and an unknown address, so
             * the form cannot be used to discover which emails exist.
             */
            throw ValidationException::withMessages([
                'email' => 'Email atau password yang Anda masukkan salah.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan. Silakan hubungi pengurus DKM.',
            ]);
        }

        $request->session()->regenerate();
        $this->users->markLoggedIn($user);

        Flash::success('Selamat datang kembali, '.$user->name.'.');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
