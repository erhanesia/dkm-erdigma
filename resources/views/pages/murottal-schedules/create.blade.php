@extends('layouts.app')

@section('title', 'Tambah Jadwal Tilawah')

@section('content')
    <x-page-header
        title="Tambah Jadwal Tilawah"
        subtitle="Tentukan kapan dan di ruangan mana murottal diputar." />

    @include('pages.murottal-schedules._form')
@endsection
