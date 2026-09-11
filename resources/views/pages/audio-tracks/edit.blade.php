@extends('layouts.app')

@section('title', 'Ubah Audio')

@section('content')
    <x-page-header :title="'Ubah ' . $track->title" subtitle="Ganti berkas hanya jika memang perlu." />

    @include('pages.audio-tracks._form')
@endsection
