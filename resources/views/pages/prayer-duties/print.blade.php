@extends('layouts.app')

@section('title', 'Cetak Petugas Sholat')

@section('content')
    {{-- The header, the range form and the sheet. Applying a range redraws
         them in place; the sidebar and the rest of the panel stay put. --}}
    <livewire:prayer-duty-print-preview />
@endsection
