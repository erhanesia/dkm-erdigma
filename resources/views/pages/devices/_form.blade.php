@php
    /** @var \App\Models\Device|null $device */
    $device ??= null;
@endphp

<form method="POST" action="{{ $device ? route('devices.update', $device) : route('devices.store') }}">
    @csrf
    @if ($device) @method('PUT') @endif

    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Data Perangkat</h2>

                    <x-form.field
                        name="name"
                        label="Nama Perangkat"
                        :value="$device?->name"
                        placeholder="Contoh: PC Ruangan CEO"
                        hint="Nama yang memudahkan Anda mengenali perangkat ini."
                        required />

                    <x-form.field
                        name="audio_zone_id"
                        label="Ruangan"
                        type="select"
                        :value="$device?->audio_zone_id"
                        :options="$zones"
                        required />

                    <x-form.field
                        name="volume"
                        label="Volume"
                        type="number"
                        :value="$device?->volume ?? 80"
                        min="0" max="100"
                        hint="0-100. Bisa diubah dari jarak jauh kapan saja."
                        required />

                    <x-form.field
                        name="notes"
                        label="Catatan"
                        type="textarea"
                        :value="$device?->notes"
                        placeholder="Misalnya: terhubung ke amplifier lantai 3, kabel lewat plafon" />

                    <div class="d-flex align-items-center justify-content-between py-2 border-top">
                        <div>
                            <div class="fw-semibold" style="font-size:.875rem;">Perangkat Aktif</div>
                            <div class="text-secondary" style="font-size:.75rem;">
                                Nonaktifkan sementara tanpa menghapus datanya
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                   @checked(old('is_active', $device?->is_active ?? true))>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-2">Cara Memasang</h2>
                    <ol class="ps-3 mb-0 text-secondary" style="font-size:.875rem; line-height:1.9;">
                        <li>Simpan perangkat ini, lalu <strong>salin token</strong> yang muncul.</li>
                        <li>Di PC/tablet ruangan, buka <code>{{ route('player.show') }}</code></li>
                        <li>Tempelkan token, lalu klik <strong>Aktifkan Audio</strong>.</li>
                        <li>Biarkan halaman itu terbuka terus.</li>
                    </ol>

                    <div class="alert alert-info py-2 px-3 mt-3 mb-0" style="font-size:.8125rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        Token hanya ditampilkan <strong>sekali</strong>. Yang tersimpan di database
                        cuma hash-nya, jadi tidak bisa dilihat lagi setelah itu.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            {{ $device ? 'Simpan Perubahan' : 'Daftarkan Perangkat' }}
        </button>
        <a href="{{ route('devices.index') }}"
       wire:navigate class="btn btn-light">Batal</a>
    </div>
</form>
