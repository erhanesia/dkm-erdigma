<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedule;

use App\Enums\DutyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FridaySchedule\StoreFridayScheduleRequest;
use App\Models\FridaySchedule;
use App\Models\User;
use App\Services\Friday\FridayScheduleService;
use App\Services\User\UserService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FridayScheduleController extends Controller
{
    public function __construct(
        private readonly FridayScheduleService $schedules,
        private readonly UserService $users,
    ) {}

    public function index(Request $request): View
    {
        $month = $request->filled('month')
            ? DateHelper::toCarbon($request->string('month')->toString().'-01')
            : DateHelper::today();

        return view('pages.friday-schedules.index', [
            'schedules' => $this->schedules->paginate(),
            'month' => $month,
            'upcoming' => $this->schedules->upcoming(4),
            'unscheduled' => $this->schedules->unscheduledFridays(),
            'statuses' => DutyStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('pages.friday-schedules.create', [
            ...$this->formData(),
            // Pre-fills the date when arriving from the "belum dijadwalkan" list.
            'suggestedDate' => $request->string('date')->toString() ?: null,
        ]);
    }

    public function store(StoreFridayScheduleRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $schedule = $this->schedules->create($request->validated(), $actor);

        Flash::success("Jadwal Jum'at ".DateHelper::formatDate($schedule->date).' berhasil dibuat.');

        return redirect()->route('friday-schedules.index');
    }

    public function show(FridaySchedule $fridaySchedule): View
    {
        return view('pages.friday-schedules.show', [
            'schedule' => $fridaySchedule->load(['khatib', 'imam', 'muadzin']),
        ]);
    }

    public function edit(FridaySchedule $fridaySchedule): View
    {
        return view('pages.friday-schedules.edit', [
            'schedule' => $fridaySchedule,
            ...$this->formData(),
        ]);
    }

    public function update(StoreFridayScheduleRequest $request, FridaySchedule $fridaySchedule): RedirectResponse
    {
        $this->schedules->update($fridaySchedule, $request->validated());

        Flash::success("Jadwal Jum'at berhasil diperbarui.");

        return redirect()->route('friday-schedules.index');
    }

    public function destroy(FridaySchedule $fridaySchedule): RedirectResponse
    {
        $this->schedules->delete($fridaySchedule);

        Flash::success("Jadwal Jum'at berhasil dihapus.");

        return redirect()->route('friday-schedules.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'people' => $this->users->dutyCandidateOptions(),
            'statuses' => DutyStatus::options(),
        ];
    }
}
