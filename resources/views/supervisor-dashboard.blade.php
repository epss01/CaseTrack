@extends('layouts.app')

@section('title', __('Office Caseload'))

@php
    use App\Models\CaseModel;

    $user = Auth::user();
    $initials = mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1);
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

            {{-- The supervisor, and the whole office's caseload in four figures. --}}
            <div class="identity-bar mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar-initials" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>

                        <div>
                            {{-- Same shape as the investigator dashboard's heading: what
                                 the page is, then whose it is, so jumping by heading
                                 lands somewhere that says where you are. --}}
                            <h1 class="identity-bar__title">
                                <span class="identity-bar__eyebrow">{{ __('Office Caseload') }}</span>
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

                    <div class="d-flex flex-wrap gap-2">
                        @can('create', CaseModel::class)
                            <a href="{{ route('cases.create') }}" class="btn btn-sm btn-light">{{ __('New Case') }}</a>
                        @endcan

                        <a href="{{ route('cases.index') }}" class="btn btn-sm btn-outline-light">{{ __('All cases') }}</a>

                        {{-- Workload scores and performance ratings live on their own
                             page and are not restated here: /workload is where P_i is
                             set, so it is the one place those figures are authoritative. --}}
                        <a href="{{ route('workload.index') }}" class="btn btn-sm btn-outline-light">{{ __('Workload') }}</a>
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
                                <span class="stat-tile__note">{{ __("of the office's active cases") }}</span>
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
                        {{-- "Total cases", not "Total assigned": office-wide every case
                             is assigned to someone, so "assigned" would distinguish
                             nothing. --}}
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'layers'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Total cases') }}</span>
                                <span class="stat-tile__value">{{ $totalCount }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- The mix across every investigator's active caseload. --}}
            @if ($activeByStatus->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Office caseload by status') }}</h2>

                        @include('partials.status-distribution', [
                            'counts' => $activeByStatus,
                            'total' => $activeCount,
                        ])
                    </div>
                </div>
            @endif

            {{-- Every proposed closure in the office, whoever holds the case: a
                 supervisor decides on any of them, not only their own. --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">{{ __('Awaiting Your Decision') }}</h2>
                    <span class="badge badge-soft-amber">{{ $pendingClosureCount }}</span>
                </div>

                <div class="card-body">
                    @if ($pendingCases->isEmpty())
                        <p class="mb-0">{{ __('No closures are waiting on a decision.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <caption class="visually-hidden">
                                    {{ __('Cases proposed for closure, with docket number, assigned investigator and the status a rejection would restore.') }}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Docket No.') }}</th>
                                        <th scope="col">{{ __('Title') }}</th>
                                        {{-- Who the case is assigned to. On a closure a
                                             supervisor proposed themselves that is not
                                             the proposer, so this does not claim to be
                                             one — the audit trail holds that. --}}
                                        <th scope="col">{{ __('Investigator') }}</th>
                                        <th scope="col">{{ __('Reverts To') }}</th>
                                        <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pendingCases as $case)
                                        {{-- No .row-pending tint: every row here is
                                             pending, so marking them all marks nothing. --}}
                                        <tr>
                                            <td class="text-nowrap">{{ $case->docket_no }}</td>
                                            <td>{{ $case->case_title }}</td>
                                            <td>{{ $case->investigator?->full_name ?? '—' }}</td>
                                            <td>{{ $case->status_before_closure ?? '—' }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('cases.closure.review', $case) }}" class="btn btn-sm btn-outline-primary">
                                                    {{ __('Review') }}<span class="visually-hidden"> {{ __('closure for case') }} {{ $case->docket_no }}</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- Outside the branch: the way through to the full office list is
                         needed whether or not anything is pending. --}}
                    <p class="mb-0 mt-3">
                        <a href="{{ route('cases.index') }}">{{ __('View all cases in the office') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
