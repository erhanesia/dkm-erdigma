<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Friday\PrayerDutyService;
use App\Support\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The prayer duty printout's preview: its header, the range form, and the sheet
 * as it will print.
 *
 * Applying a range used to be a GET form, so every change reloaded the whole
 * panel — sidebar, header and all — to redraw one table. Only the sheet and the
 * two links that carry the range differ between one range and the next, so only
 * those are redrawn now.
 *
 * The dates are held twice, on purpose. `fromInput` and `toInput` are what the
 * form holds while someone is typing; `from` and `to` are the range last
 * applied, and the only pair the sheet, the links and the address bar read. A
 * range that fails validation therefore leaves the sheet on the last good one
 * rather than drawing half of a mistake.
 */
class PrayerDutyPrintPreview extends Component
{
    /**
     * The applied range, as `Y-m-d`.
     *
     * In the address bar so a range survives a refresh and can be linked to —
     * the same `from` and `to` the editor's print button and the PDF use.
     * Locked because the range is bounded for a reason: reading it generates any
     * prayer times it is missing, so it may only change by passing through
     * `apply()`.
     */
    #[Locked]
    #[Url(keep: true)]
    public string $from = '';

    #[Locked]
    #[Url(keep: true)]
    public string $to = '';

    public string $fromInput = '';

    public string $toInput = '';

    /**
     * A range from the address bar can be anything at all — a mangled link, a
     * range too long to print. Rather than fail, the page opens on the current
     * week and says beside the field what was wrong with the one it was given.
     */
    public function mount(PrayerDutyService $duties): void
    {
        $this->fromInput = $this->from;
        $this->toInput = $this->to;

        try {
            $this->apply($duties);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());
            $this->useRange(...$duties->printPeriod([]));
        }
    }

    /**
     * Applies the range in the form.
     *
     * @throws ValidationException
     */
    public function apply(PrayerDutyService $duties): void
    {
        $this->resetErrorBag();

        $this->useRange(...$duties->printPeriod([
            'from' => $this->fromInput,
            'to' => $this->toInput,
        ]));

        // Written back so the form shows the range as applied: turned around if
        // it was typed backwards, an empty end filled in.
        $this->fromInput = $this->from;
        $this->toInput = $this->to;
    }

    public function render(PrayerDutyService $duties): View
    {
        return view('livewire.prayer-duty-print-preview', [
            ...$duties->printSheet(DateHelper::toCarbon($this->from), DateHelper::toCarbon($this->to)),
            'maxDays' => PrayerDutyService::MAX_PRINT_DAYS,
        ]);
    }

    private function useRange(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }
}
