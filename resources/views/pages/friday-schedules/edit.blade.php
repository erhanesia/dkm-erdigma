@extends('layouts.app')

@section('title', "Ubah Jadwal Jum'at")

@php
    use App\Support\Helpers\DateHelper;

    $suggestedDate = null;
@endphp

@section('content')
    <x-page-header
        :title="'Ubah Jadwal ' . DateHelper::formatDate($schedule->date)"
        subtitle="Kalau petugasnya berubah, kabari yang bersangkutan agar tidak ada yang kosong." />

    @include('pages.friday-schedules._form')
@endsection
