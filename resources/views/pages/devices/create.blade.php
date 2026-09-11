@extends('layouts.app')

@section('title', 'Daftarkan Perangkat')

@section('content')
    <x-page-header
        title="Daftarkan Perangkat"
        subtitle="Setiap ruangan butuh satu perangkat yang membuka halaman player." />

    @include('pages.devices._form')
@endsection
