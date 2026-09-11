@php
    /** @var \App\Models\AudioZone|null $zone */
    $zone ??= null;
@endphp

<form method="POST"
      action="{{ $zone ? route('audio-zones.update', $zone) : route('audio-zones.store') }}">
    @csrf
    @if ($zone) @method('PUT') @endif

    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Identitas Ruangan</h2>

                    <div class="row">
                        <div class="col-12 col-md-7">
                            <x-form.field
                                name="name"
                                label="Nama Ruangan"
                                :value="$zone?->name"
                                placeholder="Contoh: Ruangan CEO"
                                required />
                        </div>
                        <div class="col-12 col-md-5">
                            <x-form.field
                                name="code"
                                label="Kode"
                                :value="$zone?->code"
                                placeholder="ruang-ceo"
                                hint="Huruf kecil, angka, dan tanda hubung. Dipakai di URL halaman player." />
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-5">
                            <x-form.field
                                name="floor"
                                label="Lantai"
                                :value="$zone?->floor"
                                placeholder="Lantai 3" />
                        </div>
                        <div class="col-12 col-md-7">
                            <x-form.field
                                name="description"
                                label="Keterangan"
                                :value="$zone?->description"
                                placeholder="Catatan singkat tentang ruangan ini" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-1">Pengaturan Audio</h2>
                    <p class="card-subtitle mb-3">
                        Saklar utama ruangan ini. Detail per waktu sholat diatur setelah zona dibuat.
                    </p>

                    <x-form.field
                        name="default_volume"
                        label="Volume Default"
                        type="number"
                        :value="$zone?->default_volume ?? 80"
                        min="0" max="100"
                        hint="0–100. Bisa ditimpa per waktu sholat."
                        required />

                    @foreach ([
                        ['is_adhan_enabled', 'Adzan', 'Putar adzan di ruangan ini', $zone?->is_adhan_enabled ?? true],
                        ['is_murottal_enabled', 'Tilawah / Murottal', 'Putar murottal sesuai jadwal', $zone?->is_murottal_enabled ?? true],
                        ['is_active', 'Zona Aktif', 'Nonaktifkan untuk menghentikan semua audio', $zone?->is_active ?? true],
                    ] as [$field, $fieldLabel, $fieldHint, $fieldValue])
                        <div class="d-flex align-items-center justify-content-between py-2 border-top">
                            <div>
                                <div class="fw-semibold" style="font-size:.875rem;">{{ $fieldLabel }}</div>
                                <div class="text-secondary" style="font-size:.75rem;">{{ $fieldHint }}</div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" name="{{ $field }}" value="0">
                                <input type="checkbox" name="{{ $field }}" value="1"
                                       class="form-check-input"
                                       @checked(old($field, $fieldValue))>
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-3">
                        <x-form.field
                            name="sort_order"
                            label="Urutan Tampil"
                            type="number"
                            :value="$zone?->sort_order ?? 0"
                            min="0" max="999"
                            hint="Angka kecil tampil lebih dulu." />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            {{ $zone ? 'Simpan Perubahan' : 'Simpan Zona' }}
        </button>
        <a href="{{ route('audio-zones.index') }}"
       wire:navigate class="btn btn-light">Batal</a>
    </div>
</form>
