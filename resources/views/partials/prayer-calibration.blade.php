{{--
    Calibration panel.

    The comparison is shown before anything is written, because "adjust my
    prayer schedule" is not something anyone should have to accept on faith.
--}}
<div class="card mb-3" data-aos="fade-up" data-aos-delay="180" id="calibration"
     data-search-url="{{ route('calibration.cities') }}"
     data-preview-url="{{ route('calibration.preview') }}">
    <div class="card-body">
        <h2 class="card-title mb-1">
            <i class="bi bi-check2-square me-1 text-primary"></i>
            Cocokkan dengan Jadwal Resmi Kemenag
        </h2>
        <p class="card-subtitle mb-3">
            Ambil jadwal resmi daerah Anda sekali, lalu koreksi ihtiyati disetel otomatis
            supaya hasil hitungan aplikasi ini persis sama. Setelah itu jadwal tetap
            dihitung sendiri — <strong>tidak butuh internet lagi</strong>.
        </p>

        <div class="row g-2 align-items-center mb-3">
            <div class="col-12 col-md">
                <label for="calibration-city" class="form-label">Cari Kabupaten / Kota</label>
                <input type="search"
                       id="calibration-city"
                       class="form-control"
                       placeholder="Ketik minimal 3 huruf, contoh: purbalingga"
                       autocomplete="off"
                       data-calibration-search>
                <div class="form-hint" data-calibration-hint>
                    Pilih daerah yang jadwalnya biasa dipakai di kantor.
                </div>
            </div>
            <div class="col-12 col-md-auto">
                <button type="button" class="btn btn-light w-100" data-calibration-preview disabled>
                    <i class="bi bi-search me-1"></i> Bandingkan
                </button>
            </div>
        </div>

        {{-- Populated by the search; hidden until there is something to show. --}}
        <div class="list-group mb-3" data-calibration-results hidden></div>

        {{-- Comparison table, filled in by JavaScript before applying. --}}
        <div data-calibration-result hidden>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th class="text-center">Hitungan Kita</th>
                            <th class="text-center">Jadwal Resmi</th>
                            <th class="text-center">Koreksi</th>
                        </tr>
                    </thead>
                    <tbody data-calibration-rows></tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('calibration.apply') }}" class="d-flex flex-wrap gap-2">
                @csrf
                <input type="hidden" name="city_id" data-calibration-city-id>
                <button type="submit" class="btn btn-primary" data-submitting-label="Menerapkan…">
                    <i class="bi bi-check-lg me-1"></i> Terapkan Koreksi
                </button>
                <button type="button" class="btn btn-light" data-calibration-cancel>Batal</button>
            </form>
        </div>
    </div>
</div>
