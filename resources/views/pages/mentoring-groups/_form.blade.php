@php
    /** @var \App\Models\MentoringGroup|null $group */
    $group ??= null;
    $selectedMemberIds ??= [];
    $defaultCapacity ??= (int) config('dkm.mentoring.default_group_capacity');
@endphp

<form method="POST"
      action="{{ $group ? route('mentoring-groups.update', $group) : route('mentoring-groups.store') }}">
    @csrf
    @if ($group) @method('PUT') @endif

    <div class="row">
        <div class="col-12 col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Identitas Halaqah</h2>

                    <x-form.field name="name" label="Nama Halaqah" :value="$group?->name"
                                  placeholder="Contoh: Halaqah Al-Fatih" required />

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <x-form.field name="code" label="Kode" :value="$group?->code"
                                          placeholder="Dibuat otomatis"
                                          hint="Kosongkan untuk dibuatkan otomatis." />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.field name="capacity" label="Kapasitas" type="number"
                                          :value="$group?->capacity ?? $defaultCapacity"
                                          min="1" max="50"
                                          hint="Umumnya 10 orang per mentor."
                                          required />
                        </div>
                    </div>

                    <x-form.field name="mentor_id" label="Mentor (Ustadz)" type="select"
                                  :value="$group?->mentor_id" :options="$mentors"
                                  hint="Hanya orang yang ditandai sebagai mentor di halaman Pengguna."
                                  searchable required />

                    <x-form.field name="default_location" label="Lokasi Rutin" type="select"
                                  :value="$group?->default_location" :options="$locations"
                                  placeholder="Cari atau ketik tempat baru…"
                                  hint="Tempat yang belum ada di daftar akan tersimpan untuk dipakai lagi."
                                  searchable creatable />

                    <x-form.field name="description" label="Keterangan"
                                  :value="$group?->description" />

                    <div class="d-flex align-items-center justify-content-between py-2 border-top">
                        <div>
                            <div class="fw-semibold" style="font-size:.875rem;">Halaqah Aktif</div>
                            <div class="text-secondary" style="font-size:.75rem;">
                                Nonaktifkan kalau sudah tidak berjalan
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                   @checked(old('is_active', $group?->is_active ?? true))>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-1">Anggota Binaan</h2>
                    <p class="card-subtitle mb-3">
                        Hanya karyawan yang belum masuk halaqah lain yang muncul di sini —
                        satu orang tidak boleh dibina dua mentor sekaligus.
                    </p>

                    <x-form.field name="member_ids" label="Pilih Anggota" type="select"
                                  :value="$selectedMemberIds"
                                  :options="$employees->pluck('name', 'id')->all()"
                                  placeholder="Ketik nama untuk mencari…"
                                  searchable multiple />

                    @if ($employees->isEmpty())
                        <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:.8125rem;">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Tidak ada karyawan yang tersedia — semuanya sudah masuk halaqah lain.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            {{ $group ? 'Simpan Perubahan' : 'Simpan Halaqah' }}
        </button>
        <a href="{{ route('mentoring-groups.index') }}"
       wire:navigate class="btn btn-light">Batal</a>
    </div>
</form>
