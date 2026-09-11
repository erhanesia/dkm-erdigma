@extends('layouts.app')

@section('title', "Tambah Jadwal Jum'at")

@section('content')
    <x-page-header
        title="Tambah Jadwal Jum'at"
        subtitle="Tentukan khatib, imam, dan muadzin jauh hari agar tidak mendadak." />

    @include('pages.friday-schedules._form')
@endsection
