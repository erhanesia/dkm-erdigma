@extends('layouts.app')

@section('title', 'Profil Saya')

@php
    use App\Support\Helpers\DateHelper;
@endphp

@section('content')
    <x-page-header title="Profil Saya" subtitle="Data akun dan penugasan Anda." />

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card mb-3" data-aos="fade-up">
                <div class="card-body text-center">
                    <div class="d-inline-grid rounded-circle bg-primary text-white fw-bold mb-3"
                         style="width:72px;height:72px;place-items:center;font-size:1.5rem;">
                        {{ $user->initials() }}
                    </div>

                    <h2 class="card-title mb-1">{{ $user->name }}</h2>
                    <p class="card-subtitle mb-2">{{ $user->jobTitle() ?: 'Tanpa jabatan' }}</p>

                    <div class="d-flex flex-wrap justify-content-center gap-1">
                        @foreach ($user->roles as $role)
                            @php $roleEnum = \App\Enums\UserRole::tryFrom($role->name); @endphp
                            <span class="badge {{ $roleEnum?->badgeClass() ?? 'text-bg-light' }}">
                                {{ $roleEnum?->label() ?? $role->name }}
                            </span>
                        @endforeach
                    </div>

                    @if ($user->isManagedByHris())
                        <p class="form-hint mt-3 mb-0">
                            <i class="bi bi-shield-check"></i>
                            Data nama, jabatan, dan departemen dikelola sistem HRIS.
                        </p>
                    @endif
                </div>
            </div>

            @if ($group)
                <div class="card mb-3" data-aos="fade-up" data-aos-delay="60">
                    <div class="card-body">
                        <h2 class="card-title mb-1">Halaqah Saya</h2>
                        <p class="card-subtitle mb-2">{{ $group->name }}</p>
                        <div class="d-flex align-items-center gap-2" style="font-size:.875rem;">
                            <i class="bi bi-person-badge text-secondary"></i>
                            <span class="text-secondary">Mentor</span>
                            <span class="fw-medium">{{ $group->mentor?->name ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($upcomingDuties->isNotEmpty())
                <div class="card" data-aos="fade-up" data-aos-delay="120">
                    <div class="card-body pb-2">
                        <h2 class="card-title mb-0">Tugas Saya</h2>
                    </div>
                    @foreach ($upcomingDuties as $duty)
                        <div class="d-flex align-items-center gap-3 px-4 py-2 border-top">
                            <i class="bi bi-{{ $duty->prayer->icon() }} text-{{ $duty->prayer->color() }}"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="font-size:.875rem;">{{ $duty->prayer->label() }}</div>
                                <div class="text-body-tertiary" style="font-size:.75rem;">
                                    {{ DateHelper::formatLongDate($duty->date) }}
                                </div>
                            </div>
                            <span class="badge {{ $duty->status->badgeClass() }}">{{ $duty->status->label() }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="col-12 col-lg-8">
            <div class="card mb-3" data-aos="fade-up">
                <div class="card-body">
                    <h2 class="card-title mb-1">Data Akun</h2>
                    <p class="card-subtitle mb-3">Hanya email dan nomor telepon yang bisa Anda ubah sendiri.</p>

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <x-form.field name="email" label="Email" type="email"
                                              :value="$user->email" required />
                            </div>
                            <div class="col-12 col-md-6">
                                <x-form.field name="phone" label="Nomor Telepon"
                                              :value="$user->phone" placeholder="+628…" />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <x-form.field name="_name" label="Nama" :value="$user->name" disabled />
                            </div>
                            <div class="col-12 col-md-6">
                                <x-form.field name="_nik" label="NIK"
                                              :value="$user->external_employee_id ?: '—'" disabled />
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan
                        </button>
                    </form>
                </div>
            </div>

            <div class="card" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body">
                    <h2 class="card-title mb-1">Ubah Password</h2>
                    <p class="card-subtitle mb-3">
                        Minimal 10 karakter, mengandung huruf dan angka.
                    </p>

                    <form method="POST" action="{{ route('profile.password.update') }}">
                        @csrf
                        @method('PUT')

                        @if ($user->hasPassword())
                            <x-form.field name="current_password" label="Password Saat Ini"
                                          type="password" autocomplete="current-password" required />
                        @else
                            <div class="alert alert-info py-2 px-3" style="font-size:.875rem;">
                                Akun Anda belum punya password. Buat sekarang untuk bisa masuk sendiri.
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <x-form.field name="password" label="Password Baru"
                                              type="password" autocomplete="new-password" required />
                            </div>
                            <div class="col-12 col-md-6">
                                <x-form.field name="password_confirmation" label="Ulangi Password Baru"
                                              type="password" autocomplete="new-password" required />
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-key me-1"></i> Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
