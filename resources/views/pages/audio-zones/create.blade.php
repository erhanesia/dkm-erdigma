@extends('layouts.app')

@section('title', 'Tambah Zona Audio')

@section('content')
    <x-page-header
        title="Tambah Zona Audio"
        subtitle="Daftarkan ruangan yang punya speaker. Pengaturan adzan per waktu sholat dibuat otomatis." />

    @include('pages.audio-zones._form')
@endsection
