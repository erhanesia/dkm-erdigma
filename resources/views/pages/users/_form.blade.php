@php
    /** @var \App\Models\User|null $user */
    $user ??= null;
    $managedByHris = $user?->isManagedByHris() ?? false;
@endphp

<form method="POST" action="{{ $user ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if ($user) @method('PUT') @endif

    @if ($managedByHris)
        <div class="alert alert-info d-flex gap-2 align-items-start" data-aos="fade-up">
            <i class="bi bi-shield-check mt-1"></i>
            <div style="font-size:.875rem;">
                Akun ini disinkronkan dari <strong>sistem HRIS</strong>. Nama, NIK, jabatan, dan
                departemen dikunci karena akan ditimpa lagi pada sinkronisasi berikutnya.
                Yang bisa diubah di sini hanya peran, status mentor, dan akses masuk.
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-3">Data Pribadi</h2>

                    <div class="row">
                        <div class="col-12 col-md-7">
                            <x-form.field name="name" label="Nama Lengkap" :value="$user?->name"
                                          :disabled="$managedByHris" required />
                        </div>
                        <div class="col-12 col-md-5">
                            <x-form.field name="external_employee_id" label="NIK"
                                          :value="$user?->external_employee_id"
                                          :disabled="$managedByHris"
                                          placeholder="ERD-0001" />
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-7">
                            <x-form.field name="email" label="Email" type="email" :value="$user?->email" required />
                        </div>
                        <div class="col-12 col-md-5">
                            <x-form.field name="phone" label="Telepon" :value="$user?->phone" placeholder="+628…" />
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <x-form.field name="position_name" label="Jabatan" :value="$user?->position_name"
                                          :disabled="$managedByHris" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.field name="department_name" label="Departemen" :value="$user?->department_name"
                                          :disabled="$managedByHris" />
                        </div>
                    </div>

                    <x-form.field name="gender" label="Jenis Kelamin" type="select"
                                  :value="$user?->gender"
                                  :options="['Laki-laki' => 'Laki-laki', 'Perempuan' => 'Perempuan']"
                                  :disabled="$managedByHris" />
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-1">Password</h2>
                    <p class="card-subtitle mb-3">
                        {{ $user ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 10 karakter, mengandung huruf dan angka.' }}
                    </p>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <x-form.field name="password" label="Password" type="password"
                                          autocomplete="new-password" :required="! $user" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.field name="password_confirmation" label="Ulangi Password" type="password"
                                          autocomplete="new-password" :required="! $user" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title mb-1">Peran &amp; Akses</h2>
                    <p class="card-subtitle mb-3">Menentukan menu apa saja yang bisa dibuka.</p>

                    <x-form.field name="role" label="Peran"
                                  type="select"
                                  :value="$user?->primaryRole()?->value ?? \App\Enums\UserRole::Employee->value"
                                  :options="$roles"
                                  required />

                    <div class="d-flex align-items-start justify-content-between py-2 border-top">
                        <div class="pe-3">
                            <div class="fw-semibold" style="font-size:.875rem;">Mentor (Ustadz)</div>
                            <div class="text-secondary" style="font-size:.75rem;">
                                Menandai orang ini bisa dipilih sebagai pembina halaqah.
                                Peran <em>mentor</em> ikut diberikan otomatis.
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0 flex-shrink-0">
                            <input type="hidden" name="is_mentor" value="0">
                            <input type="checkbox" name="is_mentor" value="1" class="form-check-input"
                                   @checked(old('is_mentor', $user?->is_mentor ?? false))>
                        </div>
                    </div>

                    <div class="d-flex align-items-start justify-content-between py-2 border-top">
                        <div class="pe-3">
                            <div class="fw-semibold" style="font-size:.875rem;">Akun Aktif</div>
                            <div class="text-secondary" style="font-size:.75rem;">
                                Jika dimatikan, orang ini langsung tidak bisa masuk.
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0 flex-shrink-0">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                   @checked(old('is_active', $user?->is_active ?? true))>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            {{ $user ? 'Simpan Perubahan' : 'Simpan Pengguna' }}
        </button>
        <a href="{{ route('users.index') }}"
       wire:navigate class="btn btn-light">Batal</a>
    </div>
</form>
