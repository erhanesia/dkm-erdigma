<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Master;

use App\Enums\AudioTrackType;
use App\Http\Controllers\Controller;
use App\Http\Requests\MurottalSchedule\StoreMurottalScheduleRequest;
use App\Models\MurottalSchedule;
use App\Models\User;
use App\Services\Audio\AudioTrackService;
use App\Services\Audio\AudioZoneService;
use App\Services\Audio\MurottalScheduleService;
use App\Support\Helpers\DateHelper;
use App\Support\Helpers\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tilawah windows — the module that answers "kenapa di ruangan CEO mati?".
 */
class MurottalScheduleController extends Controller
{
    public function __construct(
        private readonly MurottalScheduleService $schedules,
        private readonly AudioZoneService $zones,
        private readonly AudioTrackService $tracks,
    ) {}

    public function index(): View
    {
        return view('pages.murottal-schedules.index', [
            'schedules' => $this->schedules->paginate(),
            'zones' => $this->zones->options(),
        ]);
    }

    public function create(): View
    {
        return view('pages.murottal-schedules.create', $this->formData());
    }

    public function store(StoreMurottalScheduleRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $schedule = $this->schedules->create($request->validated(), $actor);

        Flash::success('Jadwal tilawah "'.$schedule->name.'" berhasil dibuat.');

        return redirect()->route('murottal-schedules.index');
    }

    public function show(MurottalSchedule $murottalSchedule): RedirectResponse
    {
        return redirect()->route('murottal-schedules.edit', $murottalSchedule);
    }

    public function edit(MurottalSchedule $murottalSchedule): View
    {
        return view('pages.murottal-schedules.edit', [
            'schedule' => $murottalSchedule,
            ...$this->formData(),
        ]);
    }

    public function update(StoreMurottalScheduleRequest $request, MurottalSchedule $murottalSchedule): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->schedules->update($murottalSchedule, $request->validated(), $actor);

        Flash::success('Jadwal tilawah berhasil diperbarui.');

        return redirect()->route('murottal-schedules.index');
    }

    public function destroy(Request $request, MurottalSchedule $murottalSchedule): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->schedules->delete($murottalSchedule, $actor);

        Flash::success('Jadwal tilawah berhasil dihapus.');

        return redirect()->route('murottal-schedules.index');
    }

    public function toggle(Request $request, MurottalSchedule $murottalSchedule): RedirectResponse
    {
        $validated = $request->validate(['value' => ['required', 'boolean']]);

        /** @var User $actor */
        $actor = $request->user();
        $active = (bool) $validated['value'];

        $this->schedules->toggleActive($murottalSchedule, $active, $actor);

        Flash::success($active
            ? 'Jadwal tilawah diaktifkan.'
            : 'Jadwal tilawah dinonaktifkan.');

        return back();
    }

    /**
     * Shared dropdown data for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'zones' => $this->zones->options(),
            'tracks' => $this->tracks->optionsForTypes(AudioTrackType::Murottal),
            'weekdays' => DateHelper::weekdayOptions(),
        ];
    }
}
