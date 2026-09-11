@extends('layouts.app')

@section('title', $user->name)

@php use App\Support\Helpers\DateHelper; @endphp

@section('content')
    <x-page-header :title="$user->name" :subtitle="$user->jobTitle() ?: 'Tanpa jabatan.'">
        <x-slot:actions>
            <a href="{{ route('users.index') }}"
       wire:navigate class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('users.edit', $user) }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Ubah
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card mb-3" data-aos="fade-up">
                <div class="card-body">
                    <h2 class="card-title mb-3">Data Akun</h2>

                    @foreach ([
                        ['Email', $user->email, 'envelope'],
                        ['Telepon', $user->phone ?: '—', 'telephone'],
                        ['NIK', $user->external_employee_id ?: '—', 'person-vcard'],
                        ['Departemen', $user->department_name ?: '—', 'diagram-3'],
                        ['Jabatan', $user->position_name ?: '—', 'briefcase'],
                        ['Terakhir masuk', $user->last_login_at ? DateHelper::formatDateTime($user->last_login_at) : 'Belum pernah', 'clock-history'],
                    ] as [$rowLabel, $rowValue, $rowIcon])
                        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                            <i class="bi bi-{{ $rowIcon }} text-secondary"></i>
                            <span class="text-secondary" style="font-size:.8125rem;min-width:112px;">{{ $rowLabel }}</span>
                            <span class="fw-medium text-truncate" style="font-size:.875rem;">{{ $rowValue }}</span>
                        </div>
                    @endforeach

                    <div class="d-flex flex-wrap gap-1 pt-3">
                        @foreach ($user->roles as $role)
                            @php $roleEnum = \App\Enums\UserRole::tryFrom($role->name); @endphp
                            <span class="badge {{ $roleEnum?->badgeClass() ?? 'text-bg-light' }}">
                                {{ $roleEnum?->label() ?? $role->name }}
                            </span>
                        @endforeach
                        @if ($user->is_mentor)
                            <span class="badge text-bg-info">Mentor</span>
                        @endif
                        <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-danger' }}">
                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                        @if ($user->isManagedByHris())
                            <span class="badge text-bg-light border">Dari HRIS</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body">
                    <h2 class="card-title mb-1">Reset Password</h2>
                    <p class="card-subtitle mb-3">Untuk membantu orang yang lupa passwordnya.</p>

                    <form method="POST" action="{{ route('users.password.update', $user) }}">
                        @csrf
                        @method('PUT')

                        <x-form.field name="password" label="Password Baru" type="password"
                                      autocomplete="new-password" required />
                        <x-form.field name="password_confirmation" label="Ulangi Password" type="password"
                                      autocomplete="new-password" required />

                        <button type="submit" class="btn btn-light text-warning-emphasis">
                            <i class="bi bi-key me-1"></i> Reset Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card mb-3" data-aos="fade-up">
                <div class="card-body pb-2">
                    <h2 class="card-title mb-0">Halaqah yang Dibina</h2>
                </div>
                @forelse ($user->mentoredGroups as $group)
                    <a href="{{ route('mentoring-groups.show', $group) }}"
       wire:navigate
                       class="d-flex align-items-center gap-3 px-4 py-3 border-top text-body text-decoration-none">
                        <i class="bi bi-people text-primary"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $group->name }}</div>
                            <div class="text-body-tertiary" style="font-size:.75rem;">{{ $group->code }}</div>
                        </div>
                        <span class="badge {{ $group->is_active ? 'text-bg-success' : 'text-bg-light' }}">
                            {{ $group->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </a>
                @empty
                    <div class="px-4 pb-3 text-secondary" style="font-size:.875rem;">
                        Belum membina halaqah mana pun.
                    </div>
                @endforelse
            </div>

            <div class="card" data-aos="fade-up" data-aos-delay="60">
                <div class="card-body pb-2">
                    <h2 class="card-title mb-0">Keanggotaan Halaqah</h2>
                </div>
                @forelse ($user->groupMemberships->where('is_active', true) as $membership)
                    <div class="d-flex align-items-center gap-3 px-4 py-3 border-top">
                        <i class="bi bi-person-check text-info"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $membership->group->name }}</div>
                            <div class="text-body-tertiary" style="font-size:.75rem;">
                                Bergabung {{ DateHelper::formatDate($membership->joined_at) }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 pb-3 text-secondary" style="font-size:.875rem;">
                        Belum terdaftar di halaqah mana pun.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
