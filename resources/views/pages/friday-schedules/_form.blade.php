@php
    /** @var \App\Models\FridaySchedule|null $schedule */
    $schedule ??= null;
    $suggestedDate ??= null;
@endphp

<form method="POST"
      action="{{ $schedule ? route('friday-schedules.update', $schedule) : route('friday-schedules.store') }}">
    @csrf
    @if ($schedule) @method('PUT') @endif

    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Waktu &amp; Tempat</h2>

                    <div class="row">
                        <div class="col-12 col-md-5">
                            <x-form.field name="date" label="Tanggal" type="date" picker="date"
                                          :value="$schedule?->date?->toDateString() ?? $suggestedDate"
                                          hint="Harus jatuh pada hari Jum'at."
                                          required />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.field name="start_time" label="Jam Mulai" type="time"
                                          :value="$schedule?->start_time ? substr($schedule->start_time, 0, 5) : '11:50'" />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.field name="location" label="Lokasi"
                                          :value="$schedule?->location" placeholder="Musholla" />
                        </div>
                    </div>

                    <x-form.field name="theme" label="Tema Khutbah"
                                  :value="$schedule?->theme"
                                  placeholder="Contoh: Menjaga amanah dalam bekerja" />

                    <x-form.field name="notes" label="Catatan" type="textarea"
                                  :value="$schedule?->notes"
                                  placeholder="Informasi tambahan untuk petugas" />
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-1">Petugas</h2>
                    <p class="card-subtitle mb-3">
                        Satu orang boleh merangkap — khatib umumnya sekaligus menjadi imam.
                    </p>

                    <x-form.field name="khatib_id" label="Khatib (dari karyawan)" type="select"
                                  :value="$schedule?->khatib_id" :options="$people"
                                  placeholder="— Pilih, atau isi khatib tamu di bawah —"
                                  searchable />

                    <div class="divider-label mb-3">atau khatib tamu</div>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <x-form.field name="external_khatib_name" label="Nama Khatib Tamu"
                                          :value="$schedule?->external_khatib_name"
                                          placeholder="Ust. Fulan" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.field name="external_khatib_origin" label="Asal"
                                          :value="$schedule?->external_khatib_origin"
                                          placeholder="Masjid / lembaga" />
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label for="field-imam_id" class="form-label mb-0">Imam</label>

                        {{-- Khatib and imam are the same person most weeks, so the
                             common case gets a one-click shortcut instead of a
                             second search through the whole staff list. --}}
                        <button type="button"
                                class="btn btn-sm btn-light py-0 px-2"
                                style="font-size:.75rem;"
                                onclick="copyKhatibToImam()">
                            <i class="bi bi-arrow-down"></i> Sama dengan khatib
                        </button>
                    </div>

                    <x-form.field name="imam_id" label="" type="select"
                                  :value="$schedule?->imam_id" :options="$people" searchable />

                    <x-form.field name="muadzin_id" label="Muadzin" type="select"
                                  :value="$schedule?->muadzin_id" :options="$people" searchable />

                    <x-form.field name="status" label="Status" type="select"
                                  :value="$schedule?->status?->value ?? \App\Enums\DutyStatus::Assigned->value"
                                  :options="$statuses" required />
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            {{ $schedule ? 'Simpan Perubahan' : 'Simpan Jadwal' }}
        </button>
        <a href="{{ route('friday-schedules.index') }}"
       wire:navigate class="btn btn-light">Batal</a>
    </div>
</form>

@push('scripts')
    <script>
        /**
         * Copies the chosen khatib into the imam field.
         *
         * Both selects are enhanced by Tom Select, so the value has to be set
         * through its API — writing to `select.value` would update the hidden
         * element without refreshing what the user sees.
         */
        function copyKhatibToImam() {
            const khatib = document.getElementById('field-khatib_id');
            const imam = document.getElementById('field-imam_id');

            if (!khatib || !imam) {
                return;
            }

            const chosen = khatib.tomselect ? khatib.tomselect.getValue() : khatib.value;

            if (!chosen) {
                window.Swal?.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: 'Pilih khatib terlebih dahulu',
                    showConfirmButton: false,
                    timer: 2600,
                });

                return;
            }

            imam.tomselect ? imam.tomselect.setValue(chosen) : (imam.value = chosen);
        }
    </script>
@endpush
