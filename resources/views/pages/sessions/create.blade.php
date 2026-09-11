@extends('layouts.app')

@section('title', 'Jadwalkan Kegiatan')

@section('content')
    <x-page-header
        title="Jadwalkan Kegiatan"
        subtitle="Daftar hadir akan disiapkan otomatis untuk semua anggota halaqah yang dipilih." />

    @include('pages.sessions._form')
@endsection
