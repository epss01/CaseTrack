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
            @include('reports.partials.navigation')
            <div class="page-heading">
                <div>
                    <h1>{{ __('Analytics') }}</h1>
                    <p class="text-muted mb-0">{{ $officeWide ? __('Office-wide') : __('My cases') }} · {{ $user->office_region }}</p>
                </div>
                <p class="mb-0 text-muted">
                    {{ $periodFrom->translatedFormat('d M Y') }} &ndash; {{ $periodTo->translatedFormat('d M Y') }}
                    · {{ trans_choice('{1} :n day|[2,*] :n days', $metrics['period_days'], ['n' => $metrics['period_days']]) }}
                </p>
            </div>

            <div class="card filter-panel mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('reports.analytics') }}">
                        <div class="filter-fields">
                            <div class="filter-field">
                                <label for="from" class="form-label">{{ __('Period from') }}</label>
                                <input type="date" class="form-control @error('from') is-invalid @enderror"
                                       id="from" name="from" value="{{ $filters['from'] }}">
                                @error('from')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="filter-field">
                                <label for="to" class="form-label">{{ __('Period to') }}</label>
                                <input type="date" class="form-control @error('to') is-invalid @enderror"
                                       id="to" name="to" value="{{ $filters['to'] }}">
                                @error('to')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            @if ($officeWide)
                                <div class="filter-field">
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

                            <div class="filter-field">
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

                            <div class="filter-actions">
                                <a href="{{ route('reports.analytics') }}" class="btn btn-outline-secondary">
                                    {{ __('Clear') }}
                                </a>
                                <button type="submit" class="btn btn-primary">{{ __('Apply') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-3 mb-3" aria-label="{{ __('Period totals') }}">
                @foreach ([
                    ['label' => __('Cases received'), 'value' => $metrics['incoming'], 'note' => __('Cases docketed in this period.')],
                    ['label' => __('Cases resolved'), 'value' => $metrics['resolved'], 'note' => __('Cases with an FIR submitted in this period.')],
                    ['label' => __('Backlog at period end'), 'value' => $metrics['pending'], 'note' => __('Cases pending on the end date.')],
                ] as $metric)
                    <div class="col-md-4">
                        <section class="card h-100">
                            <div class="card-body">
                                <h2 class="h6 mb-1">{{ $metric['label'] }}</h2>
                                <p class="analytics-value mb-1">{{ number_format($metric['value']) }}</p>
                                <p class="text-muted mb-0">{{ $metric['note'] }}</p>
                            </div>
                        </section>
                    </div>
                @endforeach
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <figure class="card h-100 mb-0" aria-labelledby="flow-chart-title" aria-describedby="flow-chart-note">
                        <div class="card-body">
                            <h2 id="flow-chart-title">{{ __('Received versus resolved') }}</h2>
                            <p class="text-muted">{{ __('Cases received and resolved in this period.') }}</p>
                            @php
                                $chartMax = max(4, (int) ceil(max($metrics['incoming'], $metrics['resolved']) / 4) * 4);
                            @endphp
                            @foreach ([__('Received') => 'incoming', __('Resolved · FIR submitted') => 'resolved'] as $label => $key)
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between gap-3 mb-1">
                                        <span>{{ $label }}</span>
                                        <strong>{{ number_format($metrics[$key]) }}</strong>
                                    </div>
                                    <div class="analytics-bar-track" aria-hidden="true">
                                        <div class="analytics-bar {{ $key === 'resolved' ? 'analytics-bar--resolved' : '' }}" style="width: {{ $metrics[$key] / $chartMax * 100 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                            <div class="d-flex justify-content-between text-muted small" aria-hidden="true">
                                @foreach (range(0, 4) as $tick)
                                    <span>{{ number_format($chartMax * $tick / 4) }}</span>
                                @endforeach
                            </div>
                            @if ($metrics['incoming'] === 0 && $metrics['resolved'] === 0)
                                <p class="mt-3 mb-0">{{ __('No received or resolved cases in this period. Backlog may still remain.') }}</p>
                            @endif
                            <figcaption id="flow-chart-note" class="text-muted mt-3">
                                {{ __('Backlog is shown separately above.') }}
                            </figcaption>
                        </div>
                    </figure>
                </div>
                <div class="col-lg-4">
                    <section class="card h-100">
                        <div class="card-body">
                            <h2>{{ __('Key measures') }}</h2>
                            <h3 class="h6 mt-3">{{ __('Clearance Rate') }}</h3>
                            <p class="analytics-value">{{ $metrics['clearance_rate'] === null ? __('Not available') : number_format($metrics['clearance_rate'], 1).'%' }}</p>
                            <p class="text-muted">{{ $metrics['clearance_rate'] === null
                                ? __('No cases received in this period.')
                                : __('Resolved ÷ received. Above 100% means older cases were also resolved.') }}</p>
                            <h3 class="h6 mt-4">{{ __('Disposition Time') }}</h3>
                            <p class="analytics-value">{{ $metrics['disposition_days'] === null ? __('Not available') : __(':n days', ['n' => number_format($metrics['disposition_days'], 1)]) }}</p>
                            <p class="text-muted mb-0">{{ $metrics['disposition_days'] === null
                                ? __('No cases resolved in this period.')
                                : __('Backlog expressed in days at this period’s resolution rate. Not a completion forecast.') }}</p>
                        </div>
                    </section>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <p>{{ __('Resolved means the Final Investigation Report (FIR) was submitted. Closing a case does not record its FIR date.') }}</p>
                    <details class="calculation-details">
                        <summary>{{ __('How we calculate this') }}</summary>
                        <p class="mt-2">
                            <strong>{{ __('Clearance Rate:') }}</strong>
                            {{ __('cases resolved during the period divided by cases received during the period, multiplied by 100.') }}
                        </p>
                        <p class="mb-0">
                            <strong>{{ __('Disposition Time:') }}</strong>
                            {{ __('cases pending at the end of the period divided by cases resolved during the period, multiplied by the number of days in the period.') }}
                        </p>
                    </details>

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
                                '{1} :n closed case has no FIR date and cannot be placed in a resolution period.|[2,*] :n closed cases have no FIR date and cannot be placed in a resolution period.',
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
