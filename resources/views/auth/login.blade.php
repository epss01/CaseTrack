@extends('layouts.guest')

@section('title', __('Sign in'))

@section('content')
<header class="auth-heading">
    <h1>{{ __('Sign in') }}</h1>
    <p>{{ __('Welcome back. Sign in to your CaseTrack workspace.') }}</p>
</header>

@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif

<form class="auth-panel" method="POST" action="{{ route('login') }}">
    @csrf
    <div class="mb-4">
        <label for="username" class="form-label">{{ __('Username') }}</label>
        <input id="username" type="text" class="form-control @error('username') is-invalid @enderror"
               name="username" value="{{ old('username') }}" required autocomplete="username" autofocus
               @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
        @error('username')
            <span id="username-error" class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </div>
    <div class="mb-4">
        <label for="password" class="form-label">{{ __('Password') }}</label>
        <div class="input-group">
            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                   name="password" required autocomplete="current-password"
                   @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            <button id="toggle-password" class="btn btn-outline-secondary" type="button"
                    aria-label="{{ __('Show password') }}" aria-pressed="false" aria-controls="password">
                <span id="toggle-password-icon" aria-hidden="true">
                    @include('partials.icon', ['name' => 'eye', 'class' => 'ct-icon'])
                </span>
            </button>
        </div>
        @error('password')
            <span id="password-error" class="invalid-feedback d-block" role="alert">{{ $message }}</span>
        @enderror
    </div>
    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
        <label class="form-check-label" for="remember">{{ __('Keep me signed in on this workstation') }}</label>
    </div>
    <button type="submit" class="btn btn-primary w-100">{{ __('Sign in') }}</button>
</form>
<p class="auth-help">{{ __('On a shared workstation, leave “Keep me signed in” unchecked.') }}</p>
<p class="auth-help">{{ __('Forgot your password? Contact your administrator to reset it.') }}</p>
<p class="auth-footer">{{ __('Need an account?') }} <a href="{{ route('register') }}">{{ __('Request access') }}</a></p>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var input  = document.getElementById('password');
    var button = document.getElementById('toggle-password');
    var iconEl = document.getElementById('toggle-password-icon');

    /* Feather-compatible inline SVG strings matching partials/icon.blade.php */
    var SVG_ATTRS = 'width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
                  + 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
                  + 'aria-hidden="true"';

    var ICONS = {
        eye:    '<svg class="ct-icon" ' + SVG_ATTRS + '>'
                + '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>'
                + '<circle cx="12" cy="12" r="3"/>'
                + '</svg>',
        eyeOff: '<svg class="ct-icon" ' + SVG_ATTRS + '>'
                + '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>'
                + '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>'
                + '<line x1="1" y1="1" x2="23" y2="23"/>'
                + '</svg>',
    };

    button.addEventListener('click', function () {
        var showing = input.type === 'text';

        input.type          = showing ? 'password' : 'text';
        button.setAttribute('aria-pressed',  showing ? 'false' : 'true');
        button.setAttribute('aria-label',    showing ? '{{ __("Show password") }}' : '{{ __("Hide password") }}');
        iconEl.innerHTML    = showing ? ICONS.eye : ICONS.eyeOff;
    });
}());
</script>
@endpush

