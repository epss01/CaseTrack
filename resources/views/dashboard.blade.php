@extends('layouts.app')

@section('title', __('My Caseload'))

@php
    use App\Models\CaseModel;

    $user = Auth::user();
    $initials = mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1);
@endphp

@section('content')
<div class="container-fluid container-wide">
    <div class="row justify-content-center">
        <div class="col-12">

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

            {{-- The mix within the active caseload. --}}
            @if ($activeByStatus->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Active caseload by status') }}</h2>

                        @include('partials.status-distribution', [
                            'counts' => $activeByStatus,
                            'total' => $activeCount,
                        ])
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
