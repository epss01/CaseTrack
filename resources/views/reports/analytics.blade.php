@extends('layouts.app')

@section('title', __('Analytics'))

@php
    $user = Auth::user();
    $officeWide = $user->isSupervisor();
@endphp

@section('content')
<div class="container-fluid container-wide">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="identity-bar mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h1 class="identity-bar__title">
                            <span class="identity-bar__eyebrow">{{ __('Analytics') }}</span>
                            <span class="identity-bar__name">
                                {{ $officeWide ? __('Office-wide') : __('My cases') }}
                                <span class="role-pill">{{ $user->role?->role_name }}</span>
                            </span>
                        </h1>

                        <div class="identity-bar__meta">
                            {{ $user->office_region }} &middot;
                            {{ $periodFrom->translatedFormat('d M Y') }} &ndash; {{ $periodTo->translatedFormat('d M Y') }}
                            &middot;
                            {{ trans_choice('{1} :n day|[2,*] :n days', $metrics['period_days'], ['n' => $metrics['period_days']]) }}
                        </div>
                    </div>

                    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-light">
                        {{ __('View reports') }}
                    </a>
                </div>

                <div class="identity-bar__divider"></div>

                <div class="row row-cols-2 row-cols-lg-5 g-3">
                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'folder'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Incoming') }}</span>
                                <span class="stat-tile__value">{{ $metrics['incoming'] }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'check-circle'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Resolved') }}</span>
                                <span class="stat-tile__value">{{ $metrics['resolved'] }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'clock'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Pending at period end') }}</span>
                                <span class="stat-tile__value">{{ $metrics['pending'] }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'layers'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Clearance Rate') }}</span>
                                <span class="stat-tile__value">
                                    {{ $metrics['clearance_rate'] === null ? '—' : number_format($metrics['clearance_rate'], 1).'%' }}
                                </span>
                                @if ($metrics['clearance_rate'] === null)
                                    <span class="stat-tile__note">{{ __('no intake in the period') }}</span>
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'clock'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Disposition Time') }}</span>
                                <span class="stat-tile__value">
                                    {{ $metrics['disposition_days'] === null ? '—' : __(':n days', ['n' => number_format($metrics['disposition_days'], 1)]) }}
                                </span>
                                @if ($metrics['disposition_days'] === null)
                                    <span class="stat-tile__note">{{ __('nothing resolved in the period') }}</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="h6 mb-0">{{ __('Filter') }}</h2>
                </div>

                <div class="card-body">
                    <form method="GET" action="{{ route('reports.analytics') }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-sm-6 col-lg-3">
                                <label for="from" class="form-label">{{ __('Period from') }}</label>
                                <input type="date" class="form-control @error('from') is-invalid @enderror"
                                       id="from" name="from" value="{{ $filters['from'] }}">
                                @error('from')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <label for="to" class="form-label">{{ __('Period to') }}</label>
                                <input type="date" class="form-control @error('to') is-invalid @enderror"
                                       id="to" name="to" value="{{ $filters['to'] }}">
                                @error('to')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            @if ($officeWide)
                                <div class="col-sm-6 col-lg-3">
                                    <label for="investigator_id" class="form-label">{{ __('Investigator') }}</label>
                                    <select class="form-select @error('investigator_id') is-invalid @enderror"
                                            id="investigator_id" name="investigator_id">
                                        <option value="">{{ __('All investigators') }}</option>
                                        @foreach ($investigators as $investigator)
                                            <option value="{{ $investigator->id }}"
                                                @selected(($filters['investigator_id'] ?? null) == $investigator->id)>
                                                {{ $investigator->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('investigator_id')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            @endif

                            <div class="col-sm-6 col-lg-3">
                                <label for="status" class="form-label">{{ __('Status') }}</label>
                                <input type="text" class="form-control @error('status') is-invalid @enderror"
                                       id="status" name="status" value="{{ $filters['status'] ?? '' }}"
                                       list="analytics-status-options" autocomplete="off">
                                <datalist id="analytics-status-options">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}"></option>
                                    @endforeach
                                </datalist>
                                @error('status')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-12 d-flex gap-2 justify-content-end">
                                <a href="{{ route('reports.analytics') }}" class="btn btn-outline-secondary">
                                    {{ __('Clear') }}
                                </a>
                                <button type="submit" class="btn btn-primary">{{ __('Apply') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="h6 mb-0">{{ __('How these figures are calculated') }}</h2>
                </div>
                <div class="card-body">
                    <p>
                        <strong>{{ __('Clearance Rate:') }}</strong>
                        {{ __('cases resolved during the period divided by cases received during the period, multiplied by 100.') }}
                    </p>
                    <p class="mb-0">
                        <strong>{{ __('Disposition Time:') }}</strong>
                        {{ __('cases pending at the end of the period divided by cases resolved during the period, multiplied by the number of days in the period.') }}
                    </p>

                    @if ($metrics['untracked'] > 0)
                        <p class="text-muted small mb-0 mt-3">
                            {{ trans_choice(
                                '{1} :n case has no timeline recorded and is not counted.|[2,*] :n cases have no timeline recorded and are not counted.',
                                $metrics['untracked'],
                                ['n' => $metrics['untracked']]
                            ) }}
                        </p>
                    @endif

                    @if ($metrics['closed_without_date'] > 0)
                        <p class="text-muted small mb-0 mt-3">
                            {{ trans_choice(
                                '{1} :n closed case has no FIR date and is resolved but not counted in any period.|[2,*] :n closed cases have no FIR date and are resolved but not counted in any period.',
                                $metrics['closed_without_date'],
                                ['n' => $metrics['closed_without_date']]
                            ) }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
