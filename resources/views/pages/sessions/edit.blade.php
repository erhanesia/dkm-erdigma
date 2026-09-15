@extends('layouts.app')

@section('title', 'Ubah Kegiatan')

@section('content')
    <x-page-header
        :title="'Ubah ' . $session->topic"
        subtitle="Setelah kegiatan selesai, hanya status dan bacaan terakhir yang masih bisa diubah." />

    @include('pages.sessions._form')
@endsection
