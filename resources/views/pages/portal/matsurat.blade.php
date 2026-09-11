@extends('layouts.public')

@section('title', 'Al-Ma\'tsurat')
@section('description', 'Dzikir Al-Ma\'tsurat pagi dan petang — teks Arab, latin, terjemahan, beserta penghitung bacaan.')

@push('head')
    @include('partials.portal.quran-font')
@endpush

@section('content')

    {{--
        The hero says nothing that changes when the reading is switched.

        Which recension and which time are shown by the toolbar below, inside
        the region that gets replaced — so the heading can never disagree with
        the list under it.
    --}}
    <x-portal-hero>

        <div class="container position-relative text-center" data-aos>
            <span class="landing-eyebrow">
                <i class="bi bi-journal-bookmark me-1"></i> Dzikir pagi &amp; petang
            </span>

            <x-display-title class="mt-3">Al-Ma'tsurat</x-display-title>

            <p class="landing-lead mx-auto text-center">
                Ketuk kartu untuk menghitung bacaan yang berulang.
                Hitungannya tersimpan di perangkat Anda dan mulai ulang setiap hari.
            </p>
        </div>
    </x-portal-hero>

    {{-- A Livewire component: switching the reading replaces this block alone,
         and everything above it stays exactly where it is. --}}
    <livewire:matsurat-reading />

@endsection
