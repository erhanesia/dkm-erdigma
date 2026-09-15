@php
    /** @var \App\Models\AfterHoursSession|null $session */
    $session ??= null;
@endphp

<form method="POST" action="{{ $session ? route('sessions.update', $session) : route('sessions.store') }}">
    @csrf
    @if ($session) @method('PUT') @endif

    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Kegiatan</h2>

                    {{--
                        There is no separate title: every session is an after
                        hours session, so a title field said the same words on
                        every record. The subject is what tells them apart.
                    --}}
                    <x-form.field name="topic" label="Materi Kegiatan" :value="$session?->topic"
                                  placeholder="Contoh: Kajian Tafsir Surat Al-Kahfi"
                                  hint="Dipakai sebagai judul kegiatan di seluruh tampilan."
                                  required />

                    <x-form.field name="mentoring_group_id" label="Halaqah" type="select"
                                  :value="$session?->mentoring_group_id" :options="$groups"
                                  hint="Daftar hadir dan daftar peserta diambil dari anggota halaqah ini."
                                  searchable required />

                    <x-form.field name="description" label="Deskripsi" type="textarea"
                                  :value="$session?->description"
                                  placeholder="Penjelasan singkat isi kegiatan" />

                    @if ($session)
                        <x-form.field name="summary" label="Ringkasan Hasil" type="textarea"
                                      :value="$session->summary"
                                      placeholder="Diisi setelah kegiatan selesai" />
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Waktu &amp; Tempat</h2>

                    @if ($session)
                        {{-- Moving a session is Jadwal Ulang's job: it keeps the time the
                             session was first set for, which editing a field here would
                             silently overwrite. --}}
                        <div class="mb-3">
                            <div class="form-label">Waktu</div>
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <span class="fw-medium" style="font-size:.875rem;">{{ $session->humanSchedule() }}</span>
                                @if ($session->status === \App\Enums\SessionStatus::Scheduled)
                                    <a href="{{ route('sessions.reschedule.edit', $session) }}"
                                       wire:navigate class="btn btn-sm btn-light flex-shrink-0">
                                        <i class="bi bi-calendar2-week me-1"></i> Jadwal Ulang
                                    </a>
                                @endif
                            </div>
                        </div>
                    @else
                        <x-form.field name="starts_at" label="Mulai" type="datetime-local" required />

                        <x-form.field name="ends_at" label="Selesai" type="datetime-local" required />

                        <div class="d-flex align-items-start justify-content-between py-2 border-top">
                            <div class="pe-3">
                                <div class="fw-semibold" style="font-size:.875rem;">Kegiatan berulang</div>
                                <div class="text-secondary" style="font-size:.75rem;">
                                    Jadwalkan sekaligus di hari dan jam yang sama sampai tanggal tertentu.
                                </div>
                            </div>
                            <div class="form-check form-switch mb-0 flex-shrink-0">
                                <input type="hidden" name="is_recurring" value="0">
                                <input type="checkbox" name="is_recurring" value="1" class="form-check-input"
                                       data-toggle-target="#recurrence-fields"
                                       @checked(old('is_recurring'))>
                            </div>
                        </div>

                        {{-- Every two weeks first: it is how most halaqah meet. --}}
                        <div id="recurrence-fields" @style(['display: none' => ! old('is_recurring')])>
                            <x-form.field name="repeat_every_weeks" label="Ulangi" type="select"
                                          :value="2"
                                          :options="[2 => 'Setiap 2 minggu', 1 => 'Setiap minggu']"
                                          placeholder="— Pilih pengulangan —" />

                            <x-form.field name="repeat_until" label="Sampai tanggal" type="date"
                                          :hint="'Paling lama ' . $maxRecurrenceMonths . ' bulan dari kegiatan pertama. Semua pertemuan langsung dibuat, dan tanggal yang sudah punya kegiatan di halaqah ini dilewati.'" />
                        </div>
                    @endif

                    <x-form.field name="location" label="Tempat" type="select"
                                  :value="$session?->location" :options="$locations"
                                  placeholder="Cari atau ketik tempat baru…"
                                  hint="Tempat yang belum ada di daftar akan tersimpan untuk dipakai lagi."
                                  searchable creatable />

                    <x-form.field name="status" label="Status" type="select"
                                  :value="$session?->status?->value ?? \App\Enums\SessionStatus::Scheduled->value"
                                  :options="$statuses" required />

                    <div class="d-flex align-items-start justify-content-between py-2 border-top">
                        <div class="pe-3">
                            <div class="fw-semibold" style="font-size:.875rem;">Presensi QR</div>
                            <div class="text-secondary" style="font-size:.75rem;">
                                Anggota bisa scan sendiri. Hanya terbuka
                                {{ config('dkm.mentoring.check_in_opens_before') }} menit sebelum
                                sampai {{ config('dkm.mentoring.check_in_closes_after') }} menit sesudah kegiatan.
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0 flex-shrink-0">
                            <input type="hidden" name="is_qr_enabled" value="0">
                            <input type="checkbox" name="is_qr_enabled" value="1" class="form-check-input"
                                   @checked(old('is_qr_enabled', $session?->is_qr_enabled ?? true))>
                        </div>
                    </div>

                    <div class="d-flex align-items-start justify-content-between py-2 border-top">
                        <div class="pe-3">
                            <div class="fw-semibold" style="font-size:.875rem;">Tampilkan di halaman publik</div>
                            <div class="text-secondary" style="font-size:.75rem;">
                                Muncul di <a href="{{ route('portal.sessions') }}" target="_blank" rel="noopener">halaman After Hours</a>
                                yang bisa dilihat siapa saja. Hanya judul, materi, waktu, tempat,
                                dan nama pemateri yang ditampilkan &mdash; daftar peserta dan
                                catatan kehadiran tidak pernah ikut.
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0 flex-shrink-0">
                            <input type="hidden" name="is_public" value="0">
                            <input type="checkbox" name="is_public" value="1" class="form-check-input"
                                   @checked(old('is_public', $session?->is_public ?? true))>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" data-submitting-label="Menjadwalkan…">
            <i class="bi bi-check-lg me-1"></i>
            {{ $session ? 'Simpan Perubahan' : 'Jadwalkan' }}
        </button>
        <a href="{{ route('sessions.index') }}"
       wire:navigate class="btn btn-light">Batal</a>
    </div>
</form>
