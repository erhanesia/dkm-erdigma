@extends('layouts.app')

@section('title', 'Ubah Zona Audio')

@section('content')
    <x-page-header
        :title="'Ubah ' . $zone->name"
        subtitle="Perubahan langsung dikirim ke perangkat di ruangan ini." />

    @include('pages.audio-zones._form')
@endsection
