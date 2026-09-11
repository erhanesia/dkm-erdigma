@extends('layouts.app')

@section('title', 'Ubah Jadwal Tilawah')

@section('content')
    <x-page-header
        :title="'Ubah ' . $schedule->name"
        subtitle="Perubahan langsung dikirim ke perangkat di ruangan tersebut." />

    @include('pages.murottal-schedules._form')
@endsection
