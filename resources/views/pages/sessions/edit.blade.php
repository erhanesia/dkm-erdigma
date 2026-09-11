@extends('layouts.app')

@section('title', 'Ubah Kegiatan')

@section('content')
    <x-page-header
        :title="'Ubah ' . $session->topic"
        subtitle="Kegiatan yang sudah selesai tidak bisa diubah lagi." />

    @include('pages.sessions._form')
@endsection
