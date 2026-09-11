@extends('layouts.app')

@section('title', 'Unggah Audio')

@section('content')
    <x-page-header
        title="Unggah Audio"
        subtitle="Tambahkan berkas ke pustaka agar bisa dipilih di jadwal adzan dan tilawah." />

    @include('pages.audio-tracks._form')
@endsection
