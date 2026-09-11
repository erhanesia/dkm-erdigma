@use('App\Support\Helpers\DateHelper')

{{--
    Only this card re-renders when the month changes. `wire:loading` dims the
    table while the round trip is in flight, so the switch reads as "fetching"
    rather than as a page that briefly froze.
--}}
<div class="card h-100" data-aos="fade-up" data-aos-delay="60">
    <div class="card-body pb-0">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="card-title mb-0 d-flex align-items-center gap-2">
                {{ DateHelper::formatMonthYear($monthDate) }}

                <span class="spinner-border spinner-border-sm text-secondary"
                      wire:loading
                      wire:target="previousMonth, nextMonth, month"
                      aria-hidden="true"></span>
            </h2>

            <div class="d-flex gap-2 no-print">
                <button type="button"
                        class="btn btn-sm btn-light"
                        wire:click="previousMonth"
                        aria-label="Bulan sebelumnya">
                    <i class="bi bi-chevron-left"></i>
                </button>

                <input type="month"
                       wire:model.live="month"
                       class="form-control form-control-sm"
                       style="width:150px;"
                       aria-label="Pilih bulan">

                <button type="button"
                        class="btn btn-sm btn-light"
                        wire:click="nextMonth"
                        aria-label="Bulan berikutnya">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive" style="max-height: 560px; overflow-y: auto;"
         wire:loading.class="opacity-50"
         wire:target="previousMonth, nextMonth, month">
        <table class="table table-hover align-middle mb-0">
            <thead class="position-sticky top-0 bg-white" style="z-index:1;">
                <tr>
                    <th>Tanggal</th>
                    @foreach ($prayers as $prayer)
                        <th class="text-center">{{ $prayer->label() }}</th>
                    @endforeach
                    @if ($isAdmin)
                        <th class="text-end no-print"></th>
                    @endif
                </tr>
            </thead>

            <tbody>
                @forelse ($schedules as $row)
                    @php($rowDate = DateHelper::toCarbon($row->date))

                    <tr @class(['table-active' => $rowDate->toDateString() === $todayDate])>
                        <td class="text-nowrap">
                            <span @class(['fw-semibold' => $rowDate->toDateString() === $todayDate])>
                                {{ $rowDate->day }} {{ DateHelper::monthName($rowDate->month) }}
                            </span>
                            <div class="text-body-tertiary" style="font-size:.6875rem;">
                                {{ DateHelper::dayName($rowDate) }}
                                @if ($row->is_manual_override)
                                    · <i class="bi bi-pencil-fill" title="Diatur manual"></i>
                                @endif
                            </div>
                        </td>

                        @foreach ($prayers as $prayer)
                            <td class="text-center text-tabular"
                                @class(['text-body-tertiary' => ! $prayer->hasAdhan()])>
                                {{ $row->timeFor($prayer) }}
                            </td>
                        @endforeach

                        @if ($isAdmin)
                            <td class="text-end no-print">
                                @if ($row->is_manual_override)
                                    <form method="POST"
                                          action="{{ route('prayer-schedules.reset', $rowDate->toDateString()) }}"
                                          class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light"
                                                title="Kembalikan ke perhitungan otomatis">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($prayers) + ($isAdmin ? 2 : 1) }}"
                            class="text-center text-body-tertiary py-4">
                            Jadwal untuk bulan ini belum dibuat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
