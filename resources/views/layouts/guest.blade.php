<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') &middot; {{ config('app.name', 'CaseTrack') }}</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="guest-page">
    <a class="skip-link" href="#content">{{ __('Skip to main content') }}</a>
    <div class="auth-layout">
        <aside class="auth-identity" aria-label="{{ __('About CaseTrack') }}">
            <a class="navbar-brand" href="{{ route('login') }}">
                <span class="brand-mark" aria-hidden="true">CT</span>
                {{ config('app.name', 'CaseTrack') }}
            </a>
            <div class="auth-identity__message">
                <h2>{{ __('CASETRACK: A Web-Based Investigation Case Management and Analytics System') }}</h2>
                <p>{{ __('A shared workspace for Commission on Human Rights Region VIII investigators and supervisors.') }}</p>
                <div class="auth-legend" aria-label="{{ __('Deadline states') }}">
                    <span>@include('partials.icon', ['name' => 'circle']) {{ __('On track') }}</span>
                    <span>@include('partials.icon', ['name' => 'alert-triangle']) {{ __('Due soon') }}</span>
                    <span>@include('partials.icon', ['name' => 'square']) {{ __('Overdue') }}</span>
                </div>
                <p class="auth-identity__notice">{{ __('For authorized internal use. Successful sign-ins are recorded in the audit log.') }}</p>
            </div>
        </aside>
        <main class="auth-content" id="content" tabindex="-1">
            <div class="auth-form">
                @yield('content')
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html>
