@use('App\Support\Helpers\DateHelper')

{{--
    The roster as a table: one row per day, Dzuhur and Ashar side by side.

    Shared by the on-screen preview and the PDF, so what the board checks on
    screen is exactly what goes on the noticeboard.

    One row per day rather than one per prayer. dompdf cannot carry a
    `rowspan` across a page break, so a day drawn as two rows can come apart
    at the bottom of a page — the one thing a printed roster must not do.

    The day and the date share one column ("Senin, 14 September 2026"): they
    are read together anyway, and the column they no longer split goes to the
    names.

    Expects: $grid, $prayerTimes, $prayers, $tableClass (string, optional)
--}}
<table class="duty-sheet__table {{ $tableClass ?? '' }}">
    <thead>
        <tr>
            <th></th>
            @foreach ($prayers as $prayer)
                <th colspan="3" class="duty-sheet__prayer duty-sheet__group-start">{{ $prayer->label() }}</th>
            @endforeach
        </tr>
        <tr>
            <th class="duty-sheet__date">Hari, Tanggal</th>
            @foreach ($prayers as $prayer)
                <th class="duty-sheet__time duty-sheet__group-start">Waktu</th>
                <th class="duty-sheet__name">Imam</th>
                <th class="duty-sheet__name">Muadzin</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($grid as $date => $prayerRow)
            @php($schedule = $prayerTimes->get($date))

            <tr>
                <td class="duty-sheet__date">{{ DateHelper::formatLongDate($date) }}</td>

                @foreach ($prayers as $prayer)
                    @php($duty = $prayerRow[$prayer->value] ?? null)

                    <td class="duty-sheet__time duty-sheet__group-start">{{ $schedule?->timeFor($prayer) ?? '—' }}</td>
                    <td class="duty-sheet__name">{{ $duty?->imam?->name ?? '—' }}</td>
                    <td class="duty-sheet__name">{{ $duty?->muadzin?->name ?? '—' }}</td>
                @endforeach
            </tr>
        @empty
            {{-- Only Saturdays and Sundays in the range, which the roster skips. --}}
            <tr>
                <td colspan="{{ 1 + count($prayers) * 3 }}" class="duty-sheet__empty">
                    Tidak ada hari kerja (Senin–Jumat) dalam rentang ini.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
