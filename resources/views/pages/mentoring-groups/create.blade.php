@extends('layouts.app')

@section('title', 'Tambah Halaqah')

@section('content')
    <x-page-header
        title="Tambah Halaqah"
        subtitle="Tentukan mentornya, lalu pilih karyawan yang akan dibina." />

    @include('pages.mentoring-groups._form')
@endsection
