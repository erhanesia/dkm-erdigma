@extends('layouts.app')

@section('title', 'Pengaturan')

@php
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header
        title="Pengaturan"
        subtitle="Lokasi masjid dan metode hisab. Mengubahnya langsung menghitung ulang jadwal ke depan.">
        <x-slot:actions>
            <a href="{{ route('settings.activity-log') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-journal-text me-1"></i> Log Aktivitas
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('settings.update') }}"
          data-settings-form
          data-preview-url="{{ route('settings.preview') }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-12 col-lg-7">
                <div class="card mb-3" data-aos="fade-up">
                    <div class="card-body">
                        <h2 class="card-title mb-3">Identitas</h2>

                        <x-form.field name="mosque[name]" label="Nama Masjid / Musholla"
                                      :value="$values['mosque.name'] ?? config('dkm.mosque.name')"
                                      hint="Ditampilkan di header aplikasi dan layar player."
                                      required />

                        <x-form.field name="mosque[address]" label="Alamat"
                                      :value="$values['mosque.address'] ?? ''" />
                    </div>
                </div>

                <div class="card mb-3" data-aos="fade-up" data-aos-delay="60">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-1">
                            <h2 class="card-title mb-0">Lokasi &amp; Metode Hisab</h2>

                            {{-- Reads the position from the device rather than making
                                 someone look up coordinates elsewhere and retype them,
                                 where a misplaced decimal shifts the schedule. --}}
                            <button type="button" class="btn btn-sm btn-light"
                                    data-detect-location
                                    data-address-url="{{ route('settings.describe-location') }}">
                                <i class="bi bi-crosshair me-1"></i> Deteksi Lokasi Saya
                            </button>
                        </div>

                        <p class="card-subtitle mb-2">
                            Koordinat yang meleset beberapa ratus meter masih aman — jadwal hanya
                            bergeser hitungan detik. Yang berbahaya adalah salah kota.
                        </p>

                        <p class="form-hint mb-2" data-location-status>
                            Klik <strong>Deteksi Lokasi Saya</strong> saat Anda sedang berada di kantor —
                            koordinat, alamat, dan kota jadwal resmi akan terisi otomatis.
                        </p>

                        {{-- The kabupaten whose published schedule is used.
                             Derived from the detected location rather than typed,
                             so it is carried in a hidden field with a readable
                             line beside it. --}}
                        <input type="hidden"
                               name="prayer[official_city_id]"
                               id="field-official-city"
                               value="{{ old('prayer.official_city_id', $values['prayer.official_city_id'] ?? '') }}">

                        <p class="form-hint mb-3" data-official-city-note>
                            @if ($officialCityLabel)
                                <i class="bi bi-patch-check-fill text-success me-1"></i>
                                Jadwal resmi diambil dari <strong>{{ $officialCityLabel }}</strong>.
                            @else
                                <i class="bi bi-calculator me-1"></i>
                                Belum ada kota resmi — jadwal dihitung sendiri dari koordinat.
                            @endif
                        </p>

                        <div class="row">
                            <div class="col-12 col-md-4">
                                <x-form.field name="prayer[latitude]" label="Lintang" type="number" step="any"
                                              :value="$values['prayer.latitude'] ?? config('dkm.prayer.latitude')"
                                              required />
                            </div>
                            <div class="col-12 col-md-4">
                                <x-form.field name="prayer[longitude]" label="Bujur" type="number" step="any"
                                              :value="$values['prayer.longitude'] ?? config('dkm.prayer.longitude')"
                                              required />
                            </div>
                            <div class="col-12 col-md-4">
                                <x-form.field name="prayer[elevation]" label="Ketinggian (mdpl)" type="number" step="any"
                                              :value="$values['prayer.elevation'] ?? config('dkm.prayer.elevation')"
                                              required />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-7">
                                <x-form.field name="prayer[calculation_method]" label="Metode Hisab" type="select"
                                              :value="$values['prayer.calculation_method'] ?? config('dkm.prayer.calculation_method')"
                                              :options="$methods"
                                              hint="Menentukan sudut matahari untuk Subuh dan Isya. Pakai Kemenag RI agar sama dengan jadwal resmi di Indonesia."
                                              required />
                            </div>
                            <div class="col-12 col-md-5">
                                <x-form.field name="prayer[asr_method]" label="Mazhab Ashar" type="select"
                                              :value="$values['prayer.asr_method'] ?? config('dkm.prayer.asr_method')"
                                              :options="$asrMethods"
                                              hint="Hanya mempengaruhi waktu Ashar. Hanafi ± 40 menit lebih lambat."
                                              required />
                            </div>
                        </div>

                        {{-- These two settings sound similar but answer different
                             questions, and picking wrongly shifts the schedule by
                             tens of minutes — worth explaining where it is read. --}}
                        <details class="mt-1">
                            <summary class="text-primary" style="cursor:pointer;font-size:.875rem;">
                                Apa bedanya "Metode Hisab" dan "Mazhab Ashar"?
                            </summary>

                            <div class="bg-light rounded-3 p-3 mt-2" style="font-size:.875rem;line-height:1.75;">
                                <p class="mb-2">
                                    Keduanya bukan sumber data — <strong>jadwal sholat di sini dihitung
                                    sendiri dengan rumus astronomi</strong>, bukan diambil dari internet.
                                    Dua pengaturan ini cuma menentukan <em>angka acuan</em> yang dipakai
                                    dalam perhitungan itu.
                                </p>

                                <p class="mb-1"><strong>Metode Hisab</strong> — untuk Subuh &amp; Isya.</p>
                                <p class="mb-2 text-secondary">
                                    Subuh dan Isya ditandai oleh cahaya samar sebelum terbit dan sesudah
                                    terbenam. Batas "cukup gelap" itu tidak bisa diukur mutlak, jadi tiap
                                    lembaga memakai sudut matahari yang berbeda di bawah ufuk. Kemenag RI
                                    memakai 20° untuk Subuh dan 18° untuk Isya. Pengaturan ini
                                    <strong>tidak mempengaruhi Dzuhur, Ashar, dan Maghrib</strong>.
                                </p>

                                <p class="mb-1"><strong>Mazhab Ashar</strong> — khusus Ashar saja.</p>
                                <p class="mb-2 text-secondary">
                                    Ashar ditandai panjang bayangan benda. Menurut Syafi'i, Maliki, dan
                                    Hanbali, waktunya masuk saat bayangan sama panjang dengan bendanya
                                    (1×). Menurut Hanafi, saat bayangan dua kali panjang bendanya (2×) —
                                    sekitar 40 menit lebih lambat.
                                </p>

                                <p class="mb-0 text-secondary">
                                    <strong>Kenapa tidak ada "Mazhab Subuh"?</strong> Karena perbedaan
                                    Subuh bukan soal fikih, melainkan soal berapa derajat matahari
                                    dianggap sudah cukup rendah — dan itu sudah diatur oleh Metode Hisab
                                    di sebelah. Ashar satu-satunya waktu yang definisinya sendiri berbeda
                                    antar mazhab, jadi ia butuh pengaturan terpisah.
                                </p>
                            </div>
                        </details>
                    </div>
                </div>

                <div class="card" data-aos="fade-up" data-aos-delay="120">
                    <div class="card-body">
                        <h2 class="card-title mb-1">Koreksi Waktu (Ihtiyati)</h2>
                        <p class="card-subtitle mb-3">
                            Selisih menit dari hasil hitung. Boleh negatif untuk memajukan.
                        </p>

                        <div class="row g-2">
                            @foreach ($prayers as $prayer)
                                <div class="col-6 col-md-4">
                                    <label class="form-label d-flex align-items-center gap-2">
                                        <i class="bi bi-{{ $prayer->icon() }} text-{{ $prayer->color() }}"></i>
                                        {{ $prayer->label() }}
                                    </label>
                                    <div class="input-group">
                                        <input type="number"
                                               name="prayer[adjustment][{{ $prayer->value }}]"
                                               value="{{ old('prayer.adjustment.'.$prayer->value, $values['prayer.adjustment.'.$prayer->value] ?? 0) }}"
                                               min="-30" max="30"
                                               class="form-control text-center">
                                        <span class="input-group-text">mnt</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-12 col-lg-5">
                {{-- Live preview: shows what today's schedule looks like with the
                     values currently saved, so a mistake is obvious immediately. --}}
                <div class="card position-sticky" style="top: 88px;" data-aos="fade-up" data-aos-delay="60">
                    <div class="card-body">
                        <div class="d-flex align-items-baseline justify-content-between gap-2 mb-1">
                            <h2 class="card-title mb-0">Hasil Perhitungan Hari Ini</h2>
                            <span class="spinner-border spinner-border-sm text-secondary"
                                  data-preview-spinner
                                  style="display:none;"
                                  aria-hidden="true"></span>
                        </div>

                        {{-- Swapped by JS the moment a field changes, so the card
                             never claims to show saved values while displaying
                             unsaved ones. --}}
                        <p class="card-subtitle mb-3" data-preview-note>
                            Berdasarkan pengaturan yang <strong>tersimpan</strong> saat ini.
                        </p>

                        <div data-preview-list>
                            @foreach ($prayers as $prayer)
                                <div class="d-flex align-items-center gap-3 px-3 py-2 rounded-3 mb-1"
                                     style="background: {{ $loop->even ? 'transparent' : '#fafaf9' }};">
                                    <i class="bi bi-{{ $prayer->icon() }} text-{{ $prayer->color() }}"></i>
                                    <span class="flex-grow-1">{{ $prayer->label() }}</span>
                                    <span class="fw-semibold text-tabular"
                                          data-preview-time="{{ $prayer->value }}">
                                        {{ substr($preview[$prayer->value] ?? '--:--:--', 0, 5) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        {{-- What the hisab settings on the left would produce.
                             Shown only when the official schedule is winning, so
                             the dropdowns visibly still do something even though
                             they are not deciding today's times. --}}
                        <details class="preview-fallback mt-3" data-preview-fallback hidden>
                            <summary>Kalau jadwal resmi tidak tersedia</summary>

                            <div class="mt-2">
                                @foreach ($prayers as $prayer)
                                    <div class="d-flex align-items-center gap-3 px-3 py-1">
                                        <i class="bi bi-{{ $prayer->icon() }} text-body-tertiary"></i>
                                        <span class="flex-grow-1 text-secondary" style="font-size:.8125rem;">
                                            {{ $prayer->label() }}
                                        </span>
                                        <span class="text-tabular text-secondary" style="font-size:.8125rem;"
                                              data-preview-fallback-time="{{ $prayer->value }}">--:--</span>
                                    </div>
                                @endforeach

                                <p class="form-hint px-3 mt-2 mb-0">
                                    Inilah yang dihasilkan metode hisab dan mazhab yang Anda pilih.
                                </p>
                            </div>
                        </details>

                        <div class="alert alert-info py-2 px-3 mt-3 mb-0" style="font-size:.8125rem;">
                            <i class="bi bi-info-circle me-1"></i>
                            Setelah disimpan, jadwal 30 hari ke depan dihitung ulang otomatis —
                            kecuali tanggal yang sudah Anda atur manual.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Simpan Pengaturan
            </button>
        </div>
    </form>

    {{-- Outside the settings form: this panel submits on its own, and a form
         nested inside another is dropped by the HTML parser. --}}
    <div class="row g-3">
        <div class="col-12 col-lg-7">
            @include('partials.prayer-calibration')
        </div>
    </div>
@endsection
