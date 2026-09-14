@use('App\Support\Helpers\DateHelper')

{{--
    The roster as a table: one row per day, Dzuhur and Ashar side by side.

    Shared by the on-screen preview and the PDF, so what the board checks on
    screen is exactly what goes on the noticeboard.

    One row per day rather than one per prayer. dompdf cannot carry a
    `rowspan` across a page break, so a day drawn as two rows can come apart
    at the bottom of a page — the one thing a printed roster must not do.

    Expects: $grid, $prayerTimes, $prayers, $tableClass (string, optional)
--}}
<table class="duty-sheet__table {{ $tableClass ?? '' }}">
    <thead>
        <tr>
            <th colspan="2"></th>
            @foreach ($prayers as $prayer)
                <th colspan="3" class="duty-sheet__prayer duty-sheet__group-start">{{ $prayer->label() }}</th>
            @endforeach
        </tr>
        <tr>
            <th>Hari</th>
            <th>Tanggal</th>
            @foreach ($prayers as $prayer)
                <th class="duty-sheet__group-start">Waktu</th>
                <th>Imam</th>
                <th>Muadzin</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($grid as $date => $prayerRow)
            @php
                $rowDate = DateHelper::toCarbon($date);
                $schedule = $prayerTimes->get($date);
            @endphp

            <tr>
                <td class="duty-sheet__day">{{ DateHelper::dayName($rowDate) }}</td>
                <td class="duty-sheet__date">{{ DateHelper::formatDate($rowDate) }}</td>

                @foreach ($prayers as $prayer)
                    @php $duty = $prayerRow[$prayer->value] ?? null; @endphp

                    <td class="duty-sheet__time duty-sheet__group-start">{{ $schedule?->timeFor($prayer) ?? '—' }}</td>
                    <td>{{ $duty?->imam?->name ?? '—' }}</td>
                    <td>{{ $duty?->muadzin?->name ?? '—' }}</td>
                @endforeach
            </tr>
        @empty
            {{-- Only Saturdays and Sundays in the range, which the roster skips. --}}
            <tr>
                <td colspan="{{ 2 + count($prayers) * 3 }}" class="duty-sheet__empty">
                    Tidak ada hari kerja (Senin–Jumat) dalam rentang ini.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
