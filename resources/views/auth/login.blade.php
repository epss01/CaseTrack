@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Login') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="row mb-3">
                            <label for="username" class="col-md-4 col-form-label text-md-end">{{ __('Username') }}</label>

                            <div class="col-md-6">
                                <input id="username" type="text" class="form-control @error('username') is-invalid @enderror" name="username" value="{{ old('username') }}" required autocomplete="username" autofocus>

                                @error('username')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="password" class="col-md-4 col-form-label text-md-end">{{ __('Password') }}</label>

                            <div class="col-md-6">
                                <div class="input-group">
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                                    <button id="toggle-password"
                                            class="btn btn-outline-secondary"
                                            type="button"
                                            aria-label="{{ __('Show password') }}"
                                            aria-pressed="false"
                                            aria-controls="password">
                                        <span id="toggle-password-icon" aria-hidden="true">
                                            @include('partials.icon', ['name' => 'eye', 'class' => 'ct-icon'])
                                        </span>
                                    </button>
                                </div>

                                @error('password')
                                    <span class="invalid-feedback" role="alert" style="display:block">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 offset-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>

                                    <label class="form-check-label" for="remember">
                                        {{ __('Remember Me') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Login') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var input  = document.getElementById('password');
    var button = document.getElementById('toggle-password');
    var iconEl = document.getElementById('toggle-password-icon');

    /* Feather-compatible inline SVG strings matching partials/icon.blade.php */
    var SVG_ATTRS = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" '
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

