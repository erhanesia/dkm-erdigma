@php
    /** @var \App\Models\MurottalSchedule|null $schedule */
    $schedule ??= null;
    $selectedDays = old('days_of_week', $schedule?->days_of_week ?? [1, 2, 3, 4, 5]);
@endphp

<form method="POST"
      action="{{ $schedule ? route('murottal-schedules.update', $schedule) : route('murottal-schedules.store') }}">
    @csrf
    @if ($schedule) @method('PUT') @endif

    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Jadwal</h2>

                    <x-form.field name="name" label="Nama Jadwal" :value="$schedule?->name"
                                  placeholder="Contoh: Murottal pagi sebelum kerja" required />

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <x-form.field name="audio_zone_id" label="Ruangan" type="select"
                                          :value="$schedule?->audio_zone_id" :options="$zones" required />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.field name="audio_track_id" label="Audio" type="select"
                                          :value="$schedule?->audio_track_id" :options="$tracks"
                                          placeholder="— Pakai audio default —"
                                          searchable />
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 col-md-4">
                            <x-form.field name="start_time" label="Jam Mulai" type="time"
                                          :value="$schedule?->start_time ? substr($schedule->start_time, 0, 5) : '07:00'"
                                          required />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.field name="end_time" label="Jam Selesai" type="time"
                                          :value="$schedule?->end_time ? substr($schedule->end_time, 0, 5) : '08:00'"
                                          required />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.field name="volume" label="Volume" type="number"
                                          :value="$schedule?->volume ?? 60" min="0" max="100" required />
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Hari <span class="required-mark">*</span>
                        </label>

                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($weekdays as $dayNumber => $dayName)
                                <div>
                                    <input type="checkbox"
                                           class="btn-check"
                                           name="days_of_week[]"
                                           value="{{ $dayNumber }}"
                                           id="day-{{ $dayNumber }}"
                                           @checked(in_array($dayNumber, (array) $selectedDays))>
                                    <label class="btn btn-light" for="day-{{ $dayNumber }}">
                                        {{ $dayName }}
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        @error('days_of_week')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Perilaku</h2>

                    @foreach ([
                        ['stops_before_adhan', 'Berhenti Sebelum Adzan',
                         'Jendela tilawah dipotong otomatis kalau ada adzan di dalamnya. Sangat disarankan.',
                         $schedule?->stops_before_adhan ?? true],
                        ['is_loop', 'Ulangi Terus',
                         'Audio diputar berulang sampai jendela waktunya habis.',
                         $schedule?->is_loop ?? true],
                        ['is_active', 'Aktif',
                         'Nonaktifkan sementara tanpa menghapus jadwalnya.',
                         $schedule?->is_active ?? true],
                    ] as [$field, $switchLabel, $switchHint, $switchValue])
                        <div class="d-flex align-items-start justify-content-between py-2 border-bottom">
                            <div class="pe-3">
                                <div class="fw-semibold" style="font-size:.875rem;">{{ $switchLabel }}</div>
                                <div class="text-secondary" style="font-size:.75rem;">{{ $switchHint }}</div>
                            </div>
                            <div class="form-check form-switch mb-0 flex-shrink-0">
                                <input type="hidden" name="{{ $field }}" value="0">
                                <input type="checkbox" name="{{ $field }}" value="1" class="form-check-input"
                                       @checked(old($field, $switchValue))>
                            </div>
                        </div>
                    @endforeach

                    <div class="alert alert-info py-2 px-3 mt-3 mb-0" style="font-size:.8125rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        Dua jadwal di ruangan dan hari yang sama tidak boleh bertabrakan jamnya —
                        sistem akan menolaknya.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            {{ $schedule ? 'Simpan Perubahan' : 'Simpan Jadwal' }}
        </button>
        <a href="{{ route('murottal-schedules.index') }}"
       wire:navigate class="btn btn-light">Batal</a>
    </div>
</form>
