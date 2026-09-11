<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Beranda') — {{ config('app.name') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/logo-mark.png') }}">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    @livewireStyles

    @stack('head')
</head>
<body>
    <div class="app-shell">
        {{--
            The sidebar and topbar are kept across navigations rather than
            re-rendered. Two reasons:

            1. A replaced element cannot animate — the new markup simply appears
               in its final state. Persisting them is what lets the active menu
               item slide instead of jumping.
            2. The sidebar's scroll position and the running clock survive,
               instead of resetting on every page.

            The active menu item is then updated in JavaScript, in
            `syncActiveNavLink()`.
        --}}
        @persist('sidebar')
            @include('partials.sidebar')
        @endpersist

        <div class="sidebar-backdrop" aria-hidden="true"></div>

        <div class="app-main">
            @persist('topbar')
                @include('partials.topbar')
            @endpersist

            <main class="app-content">
                @yield('content')
            </main>
        </div>
    </div>

    {{-- Read once by initFlash() and turned into a toast. --}}
    @if ($flash = \App\Support\Helpers\Flash::pull())
        <script type="application/json" id="flash-payload">@json($flash)</script>
    @endif

    @stack('scripts')

    @livewireScripts
</body>
</html>
