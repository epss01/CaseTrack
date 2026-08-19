<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Every page shared one title, so browser tabs were indistinguishable. --}}
    <title>@hasSection('title')@yield('title') &middot; @endif{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div id="app">
        <a class="skip-link" href="#content">{{ __('Skip to main content') }}</a>

        {{-- The same shell the table-first pages use (dashboards, /reports,
             /alerts), so the brand and the nav links line up with the card
             edge below them instead of sitting 20px inboard of it. One width
             for the chrome rather than one per route: the pages still on
             .container centre a col-md-8/10/11 card that reaches neither
             cap, so there is no edge on them for the navbar to disagree
             with — including the auth screens, whose form is unchanged. --}}
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container-fluid container-wide">
                <a class="navbar-brand" href="{{ url('/') }}">
                    <span class="brand-mark" aria-hidden="true">CT</span>
                    {{ config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        @auth
                            {{-- Both case-handling roles get a dashboard at /home now:
                                 the investigator's own caseload, or the office-wide one.
                                 Anyone else lands on the plain page and has no use for
                                 the link. --}}
                            @if (Auth::user()->hasRole(...App\Models\Role::CASE_HANDLING))
                                <li class="nav-item">
                                    <a class="nav-link @if (request()->routeIs('home')) active @endif"
                                       @if (request()->routeIs('home')) aria-current="page" @endif
                                       href="{{ route('home') }}">{{ __('Dashboard') }}</a>
                                </li>
                            @endif

                            @can('viewAny', App\Models\CaseModel::class)
                                <li class="nav-item">
                                    <a class="nav-link @if (request()->routeIs('cases.*')) active @endif"
                                       @if (request()->routeIs('cases.*')) aria-current="page" @endif
                                       href="{{ route('cases.index') }}">{{ __('Cases') }}</a>
                                </li>
                            @endcan

                            {{-- Same condition as the dashboard: /alerts is scoped by
                                 the same rule, an investigator's own cases or the whole
                                 office. --}}
                            @if (Auth::user()->hasRole(...App\Models\Role::CASE_HANDLING))
                                <li class="nav-item">
                                    <a class="nav-link @if (request()->routeIs('alerts.*')) active @endif"
                                       @if (request()->routeIs('alerts.*')) aria-current="page" @endif
                                       href="{{ route('alerts.index') }}">{{ __('Alerts') }}</a>
                                </li>
                            @endif

                            {{-- Same condition again: a report is scoped by the same
                                 rule as the dashboard and /alerts, an investigator's
                                 own cases or the whole office. --}}
                            @if (Auth::user()->hasRole(...App\Models\Role::CASE_HANDLING))
                                <li class="nav-item">
                                    <a class="nav-link @if (request()->routeIs('reports.*')) active @endif"
                                       @if (request()->routeIs('reports.*')) aria-current="page" @endif
                                       href="{{ route('reports.index') }}">{{ __('Reports') }}</a>
                                </li>
                            @endif

                            @if (Auth::user()->isSupervisor())
                                <li class="nav-item">
                                    <a class="nav-link @if (request()->routeIs('workload.*')) active @endif"
                                       @if (request()->routeIs('workload.*')) aria-current="page" @endif
                                       href="{{ route('workload.index') }}">{{ __('Workload') }}</a>
                                </li>

                                {{-- Bulk case intake, Supervisor-only — see routes/web.php. --}}
                                <li class="nav-item">
                                    <a class="nav-link @if (request()->routeIs('cases.import.*')) active @endif"
                                       @if (request()->routeIs('cases.import.*')) aria-current="page" @endif
                                       href="{{ route('cases.import.create') }}">{{ __('Import Cases') }}</a>
                                </li>
                            @endif

                            @if (Auth::user()->isAdmin())
                                <li class="nav-item">
                                    <a class="nav-link @if (request()->routeIs('admin.registrations.*')) active @endif"
                                       @if (request()->routeIs('admin.registrations.*')) aria-current="page" @endif
                                       href="{{ route('admin.registrations.index') }}">{{ __('Registrations') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link @if (request()->routeIs('admin.users.*')) active @endif"
                                       @if (request()->routeIs('admin.users.*')) aria-current="page" @endif
                                       href="{{ route('admin.users.index') }}">{{ __('Accounts') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link @if (request()->routeIs('admin.audit-logs.*')) active @endif"
                                       @if (request()->routeIs('admin.audit-logs.*')) aria-current="page" @endif
                                       href="{{ route('admin.audit-logs.index') }}">{{ __('Audit Log') }}</a>
                                </li>
                            @endif
                        @endauth
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                            @endif

                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->full_name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    {{-- A real submit button, not an anchor pointing at a
                                         POST-only route: if the script failed to attach, the
                                         scaffold's version navigated to /logout and 405'd. --}}
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf

                                        <button type="submit" class="dropdown-item">{{ __('Logout') }}</button>
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4" id="content">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
