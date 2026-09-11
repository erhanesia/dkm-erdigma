@extends('layouts.app')

@section('title', 'Ubah Pengguna')

@section('content')
    <x-page-header :title="'Ubah ' . $user->name" subtitle="Peran dan status mentor dikelola dari sini." />

    @include('pages.users._form')
@endsection
