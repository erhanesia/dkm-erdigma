@extends('layouts.app')

@section('title', 'Pustaka Audio')

@php
    use App\Support\Helpers\DateHelper;
    use App\Support\Helpers\NumberHelper;
@endphp

@section('content')
    <x-page-header
        title="Pustaka Audio"
        subtitle="Berkas adzan, tarhim, iqamah, murottal, dan nada uji speaker.">
        <x-slot:actions>
            <a href="{{ route('audio-tracks.create') }}"
       wire:navigate class="btn btn-primary">
                <i class="bi bi-upload me-1"></i> Unggah Audio
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('audio-tracks.index')" placeholder="Cari judul atau nama qari…">
        <div class="col-6 col-md-auto">
            <select name="filter[type]" class="form-select">
                <option value="">Semua jenis</option>
                @foreach ($types as $typeValue => $typeLabel)
                    <option value="{{ $typeValue }}" @selected(request()->input('filter.type') === $typeValue)>
                        {{ $typeLabel }}
                    </option>
                @endforeach
            </select>
        </div>
    </x-filter-bar>

    <div class="table-card" data-aos="fade-up">
        @if ($tracks->isEmpty())
            <x-empty-state
                icon="file-earmark-music"
                title="Pustaka masih kosong"
                text="Unggah minimal satu berkas adzan, satu murottal, dan satu nada uji speaker agar penjadwalan bisa berjalan.">
                <x-slot:action>
                    <a href="{{ route('audio-tracks.create') }}"
       wire:navigate class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i> Unggah Audio
                    </a>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Jenis</th>
                            <th class="text-center">Durasi</th>
                            <th class="text-center">Ukuran</th>
                            <th class="text-center">Default</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tracks as $track)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="d-grid rounded-3 bg-light text-{{ $track->type->color() }} flex-shrink-0"
                                              style="width:34px;height:34px;place-items:center;">
                                            <i class="bi bi-{{ $track->type->icon() }}"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ route('audio-tracks.show', $track) }}"
       wire:navigate
                                               class="fw-semibold text-body d-block text-truncate">
                                                {{ $track->title }}
                                            </a>
                                            @if ($track->reciter)
                                                <div class="text-body-tertiary text-truncate" style="font-size:.75rem;">
                                                    {{ $track->reciter }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge {{ $track->type->badgeClass() }}">
                                        {{ $track->type->label() }}
                                    </span>
                                </td>

                                <td class="text-center text-tabular" style="font-size:.875rem;">
                                    {{ NumberHelper::duration($track->duration_seconds) }}
                                </td>

                                <td class="text-center text-tabular" style="font-size:.875rem;">
                                    {{ NumberHelper::fileSize($track->file_size) }}
                                </td>

                                <td class="text-center">
                                    @if ($track->is_default)
                                        <i class="bi bi-star-fill text-warning" title="Audio default untuk jenis ini"></i>
                                    @else
                                        <form method="POST" action="{{ route('audio-tracks.default', $track) }}"
                                              class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-link p-0 border-0 text-body-tertiary"
                                                    title="Jadikan default untuk {{ $track->type->label() }}">
                                                <i class="bi bi-star"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('audio-tracks.show', $track) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('audio-tracks.edit', $track) }}"
       wire:navigate
                                           class="btn btn-sm btn-light" title="Ubah">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <x-delete-button
                                            :action="route('audio-tracks.destroy', $track)"
                                            :title="'Hapus ' . $track->title . '?'"
                                            confirm="Berkas audionya ikut terhapus dari penyimpanan."
                                            icon-only />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($tracks->hasPages())
                <div class="p-3 border-top">{{ $tracks->links() }}</div>
            @endif
        @endif
    </div>
@endsection
