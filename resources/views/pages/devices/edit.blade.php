@extends('layouts.app')

@section('title', 'Ubah Perangkat')

@section('content')
    <x-page-header :title="'Ubah ' . $device->name" subtitle="Perubahan berlaku pada sinkronisasi berikutnya." />

    @include('pages.devices._form')
@endsection
