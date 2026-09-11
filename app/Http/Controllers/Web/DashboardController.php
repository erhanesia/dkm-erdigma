<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Report\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One route, three views — what a person sees depends on what they are
 * responsible for, so a mentor is not shown device alerts they cannot act on.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdministrator()) {
            return view('pages.dashboard.admin', $this->dashboard->forAdministrator());
        }

        if ($user->isMentor()) {
            return view('pages.dashboard.mentor', $this->dashboard->forMentor($user));
        }

        return view('pages.dashboard.employee', $this->dashboard->forEmployee($user));
    }
}
