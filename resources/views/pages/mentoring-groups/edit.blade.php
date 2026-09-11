@extends('layouts.app')

@section('title', 'Ubah Halaqah')

@section('content')
    <x-page-header
        :title="'Ubah ' . $group->name"
        subtitle="Anggota yang tidak lagi dipilih akan dikeluarkan dari halaqah ini." />

    @include('pages.mentoring-groups._form')
@endsection
