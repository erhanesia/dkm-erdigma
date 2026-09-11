@php
    /** @var \App\Models\AudioTrack|null $track */
    $track ??= null;
    $maxMb = (int) (config('dkm.audio.max_size_kb') / 1024);
@endphp

<form method="POST"
      action="{{ $track ? route('audio-tracks.update', $track) : route('audio-tracks.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if ($track) @method('PUT') @endif

    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Data Audio</h2>

                    <x-form.field name="title" label="Judul" :value="$track?->title"
                                  placeholder="Contoh: Adzan Makkah - Ali Mulla" required />

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <x-form.field name="type" label="Jenis" type="select"
                                          :value="$track?->type?->value"
                                          :options="$types"
                                          hint="Menentukan di mana audio ini bisa dipakai."
                                          required />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.field name="reciter" label="Qari / Sumber" :value="$track?->reciter"
                                          placeholder="Nama pembaca" />
                        </div>
                    </div>

                    <x-form.field name="duration_seconds" label="Durasi (detik)" type="number"
                                  :value="$track?->duration_seconds" min="1" max="7200"
                                  hint="Opsional. Dipakai untuk memperkirakan kapan audio selesai." />

                    <div class="mb-3">
                        <label for="field-file" class="form-label">
                            Berkas Audio
                            @unless ($track)<span class="required-mark">*</span>@endunless
                        </label>
                        <input type="file" id="field-file" name="file"
                               @class(['form-control', 'is-invalid' => $errors->has('file')])
                               accept="audio/*"
                               @unless ($track) required @endunless>
                        @error('file')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-hint">
                            Format: {{ implode(', ', (array) config('dkm.audio.allowed_mimes')) }}.
                            Maksimal {{ $maxMb }} MB.
                            @if ($track)
                                Biarkan kosong jika tidak ingin mengganti berkas.
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Status</h2>

                    <div class="d-flex align-items-start justify-content-between py-2 border-bottom">
                        <div class="pe-3">
                            <div class="fw-semibold" style="font-size:.875rem;">Jadikan Default</div>
                            <div class="text-secondary" style="font-size:.75rem;">
                                Dipakai otomatis kalau zona belum memilih audio sendiri.
                                Hanya boleh satu per jenis.
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0 flex-shrink-0">
                            <input type="hidden" name="is_default" value="0">
                            <input type="checkbox" name="is_default" value="1" class="form-check-input"
                                   @checked(old('is_default', $track?->is_default ?? false))>
                        </div>
                    </div>

                    <div class="d-flex align-items-start justify-content-between py-2">
                        <div class="pe-3">
                            <div class="fw-semibold" style="font-size:.875rem;">Aktif</div>
                            <div class="text-secondary" style="font-size:.75rem;">
                                Audio nonaktif tidak bisa dipilih di jadwal mana pun.
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0 flex-shrink-0">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                   @checked(old('is_active', $track?->is_active ?? true))>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="card-title mb-2">Catatan</h2>
                    <p class="text-secondary mb-0" style="font-size:.8125rem;line-height:1.7;">
                        Berkas disimpan di penyimpanan privat. Perangkat player mengunduhnya
                        lewat endpoint yang butuh token, jadi pustaka ini tidak bisa diakses
                        sembarang orang dari internet.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            {{ $track ? 'Simpan Perubahan' : 'Unggah' }}
        </button>
        <a href="{{ route('audio-tracks.index') }}"
       wire:navigate class="btn btn-light">Batal</a>
    </div>
</form>
