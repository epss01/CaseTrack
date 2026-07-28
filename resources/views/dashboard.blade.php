@extends('layouts.app')

@section('title', __('My Caseload'))

@php
    use App\Models\CaseModel;

    $user = Auth::user();
    $initials = mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1);

    /*
     * A colour per status, stable everywhere.
     *
     * An earlier version assigned the blue ramp by rank within whoever's caseload
     * was on screen. That made the same status render a different shade depending
     * on whose dashboard you were looking at, and shift for one investigator as
     * their mix changed — which defeats the point of having a legend at all.
     *
     * So: two behavioural constants keep reserved colours (Pending Closure is
     * waiting on a supervisor, Closed is out of the active set), the two free-text
     * values already in circulation get a fixed shade each, and anything new is
     * hashed to the ramp so it is at least consistent from one day to the next.
     *
     * This is presentation only. It constrains nothing, validates nothing, and a
     * status not listed here still renders correctly — the status vocabulary is
     * still unratified and this view does not pretend otherwise.
     */
    // Steps are two rungs apart, not adjacent: #3b82f6 beside #60a5fa rendered as
    // one solid blue bar, which defeats the only thing the bar is for.
    $ramp = ['#1d4ed8', '#3b82f6', '#93c5fd', '#1e3a8a'];

    $fixed = [
        CaseModel::STATUS_PENDING_CLOSURE => '#b45309',
        CaseModel::STATUS_CLOSED => '#94a3b8',
        CaseModel::STATUS_DOCKETED => '#1d4ed8',
        'Under investigation' => '#3b82f6',
        'For review' => '#93c5fd',
    ];

    $colourFor = fn (string $status) => $fixed[$status]
        ?? $ramp[crc32($status) % count($ramp)];
@endphp

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-11 col-xl-10">

            @if (session('status'))
                <div class="alert alert-success" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            {{-- The investigator, and their caseload in four figures. --}}
            <div class="identity-bar mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar-initials" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>

                        <div>
                            {{-- The heading leads with what the page is, then who it
                                 belongs to: a proper name alone told a screen-reader
                                 user jumping by heading nothing about where they had
                                 landed. The name stays the visual anchor. --}}
                            <h1 class="identity-bar__title">
                                <span class="identity-bar__eyebrow">{{ __('My Caseload') }}</span>
                                <span class="identity-bar__name">
                                    {{ $user->full_name }}
                                    <span class="role-pill">{{ $user->role?->role_name }}</span>
                                </span>
                            </h1>

                            <div class="identity-bar__meta">
                                {{ $user->office_region }} &middot; {{ now()->translatedFormat('l, d F Y') }}
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        @can('create', CaseModel::class)
                            <a href="{{ route('cases.create') }}" class="btn btn-sm btn-light">{{ __('New Case') }}</a>
                        @endcan

                        <a href="{{ route('cases.index') }}" class="btn btn-sm btn-outline-light">{{ __('My cases') }}</a>
                    </div>
                </div>

                <div class="identity-bar__divider"></div>

                <div class="row row-cols-2 row-cols-lg-4 g-3">
                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'folder'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Active cases') }}</span>
                                <span class="stat-tile__value">{{ $activeCount }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'clock'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Awaiting approval') }}</span>
                                <span class="stat-tile__value">{{ $pendingClosureCount }}</span>
                                <span class="stat-tile__note">{{ __('of your active cases') }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'check-circle'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Closed') }}</span>
                                <span class="stat-tile__value">{{ $closedCount }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'layers'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Total assigned') }}</span>
                                <span class="stat-tile__value">{{ $totalCount }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- The mix within the active caseload. The bar is decoration; the
                 legend beneath it carries the actual figures. --}}
            @if ($activeByStatus->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Active caseload by status') }}</h2>

                        <div class="progress-stacked mb-3" style="height: .5rem;" aria-hidden="true">
                            @foreach ($activeByStatus as $status => $count)
                                <div class="progress" style="width: {{ round($count / max($activeCount, 1) * 100, 2) }}%; height: .5rem;">
                                    <div class="progress-bar" style="background-color: {{ $colourFor($status) }};"></div>
                                </div>
                            @endforeach
                        </div>

                        <ul class="list-inline mb-0 small">
                            @foreach ($activeByStatus as $status => $count)
                                <li class="list-inline-item me-4">
                                    <span class="legend-dot" style="background-color: {{ $colourFor($status) }};" aria-hidden="true"></span>
                                    {{ $status }} <strong>{{ $count }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">{{ __('Active Cases') }}</h2>
                    <span class="badge badge-soft-blue">{{ $activeCount }}</span>
                </div>

                <div class="card-body">
                    @if ($cases->isEmpty())
                        <p>{{ __('No active cases are assigned to you.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <caption class="visually-hidden">
                                    {{ __('Your active cases, with docket number, status, complexity and timeline dates.') }}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Docket No.') }}</th>
                                        <th scope="col">{{ __('Title') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Complexity') }}</th>
                                        <th scope="col">{{ __('Date of Docket') }}</th>
                                        <th scope="col">{{ __('Submission (120th Day)') }}</th>
                                        <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cases as $case)
                                        <tr @class([
                                            'row-pending table-warning' => $case->status === CaseModel::STATUS_PENDING_CLOSURE,
                                        ])>
                                            {{-- nowrap: the wrapper already scrolls, so letting a
                                                 docket number break across four lines on a phone
                                                 costs legibility and buys nothing. --}}
                                            <td class="text-nowrap">{{ $case->docket_no }}</td>
                                            <td>{{ $case->case_title }}</td>
                                            <td>@include('cases.partials.status-badge', ['status' => $case->status])</td>
                                            <td>
                                                <span class="weight-meter" aria-hidden="true">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <span @class(['weight-meter__seg', 'is-filled' => $i <= $case->complexity_weight])></span>
                                                    @endfor
                                                </span>
                                                <span class="text-muted small ms-1" aria-hidden="true">{{ $case->complexity_weight }}</span>
                                                <span class="visually-hidden">{{ __('Complexity :n of 5', ['n' => $case->complexity_weight]) }}</span>
                                            </td>
                                            <td class="text-nowrap">{{ $case->timeline?->date_of_docket?->format('d M Y') ?? '—' }}</td>
                                            <td class="text-nowrap">{{ $case->timeline?->submission_120th_day?->format('d M Y') ?? '—' }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('cases.show', $case) }}" class="btn btn-sm btn-outline-primary">
                                                    {{ __('View') }}<span class="visually-hidden"> {{ __('case') }} {{ $case->docket_no }}</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- Outside the branch above: an investigator whose cases are all closed still needs the way out. --}}
                    <p class="mb-0 mt-3">
                        <a href="{{ route('cases.index') }}">{{ __('View all my cases') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
