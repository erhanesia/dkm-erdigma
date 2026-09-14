@use('App\Support\Helpers\DateHelper')

{{--
    The roster as a PDF document, rendered by dompdf.

    Styled inline and on its own: dompdf reads neither Vite's bundle nor
    Bootstrap, and supports only part of CSS — so tables, borders and plain
    blocks, nothing that depends on flexbox or grid. DejaVu Sans ships with
    dompdf and carries the dashes and the apostrophe in "Jum'at".

    Colours are the site's own tokens, written out: brand-800 for the mosque
    line, brand-50 behind the prayer names, the stone neutrals everywhere else.

    Expects: $from, $to, $grid, $prayerTimes, $prayers, $mosqueName, $printedAt
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jadwal Petugas Sholat {{ DateHelper::formatDate($from) }} – {{ DateHelper::formatDate($to) }}</title>
    <style>
        @page {
            margin: 14mm 12mm 16mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #1c1917;
        }

        .sheet-head {
            margin-bottom: 12pt;
            padding-bottom: 8pt;
            border-bottom: 1.5pt solid #115e56;
        }

        .sheet-mosque {
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #115e56;
        }

        .sheet-title {
            margin-top: 2pt;
            font-size: 16pt;
            font-weight: bold;
        }

        .sheet-period {
            margin-top: 2pt;
            font-size: 10pt;
            color: #44403c;
        }

        /* On every page, inside the bottom margin. */
        .sheet-foot {
            position: fixed;
            right: 0;
            bottom: -9mm;
            left: 0;
            font-size: 7pt;
            color: #78716c;
        }

        .duty-sheet__table {
            width: 100%;
            border-collapse: collapse;
        }

        .duty-sheet__table th,
        .duty-sheet__table td {
            padding: 5pt 6pt;
            vertical-align: middle;
            border: 0.6pt solid #d6d3d1;
        }

        .duty-sheet__table thead th {
            font-size: 7.5pt;
            letter-spacing: 0.04em;
            text-align: left;
            text-transform: uppercase;
            color: #44403c;
            background: #f5f5f4;
        }

        .duty-sheet__table thead th.duty-sheet__prayer {
            font-size: 8.5pt;
            text-align: center;
            color: #115e56;
            background: #ecfdf7;
        }

        /* Where one prayer's columns end and the next begin. */
        .duty-sheet__table .duty-sheet__group-start {
            border-left: 1.2pt solid #78716c;
        }

        .duty-sheet__table tbody tr {
            page-break-inside: avoid;
        }

        .duty-sheet__table tbody tr:nth-child(even) td {
            background: #fafaf9;
        }

        .duty-sheet__day {
            font-weight: bold;
        }

        .duty-sheet__date,
        .duty-sheet__time {
            white-space: nowrap;
        }

        .duty-sheet__time {
            font-weight: bold;
            text-align: center;
        }

        .duty-sheet__empty {
            padding: 14pt;
            text-align: center;
            color: #78716c;
        }
    </style>
</head>
<body>
    <div class="sheet-foot">
        Dicetak {{ DateHelper::formatDateTime($printedAt) }} dari panel DKM.
    </div>

    <div class="sheet-head">
        <div class="sheet-mosque">{{ $mosqueName }}</div>
        <div class="sheet-title">Jadwal Petugas Sholat Dzuhur &amp; Ashar</div>
        <div class="sheet-period">{{ DateHelper::formatDate($from) }} – {{ DateHelper::formatDate($to) }}</div>
    </div>

    @include('pages.prayer-duties._sheet', [
        'grid' => $grid,
        'prayerTimes' => $prayerTimes,
        'prayers' => $prayers,
    ])
</body>
</html>
