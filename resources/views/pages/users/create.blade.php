@extends('layouts.app')

@section('title', 'Tambah Pengguna')

@section('content')
    <x-page-header
        title="Tambah Pengguna"
        subtitle="Untuk orang yang tidak ada di HRIS — misalnya khatib tamu dari luar." />

    @include('pages.users._form')
@endsection
