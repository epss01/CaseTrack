@extends('layouts.app')

@section('title', __('Office Caseload'))

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

            {{-- The supervisor, and the whole office's caseload in three figures. --}}
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

                <div class="row row-cols-1 row-cols-md-3 g-3">
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
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Office caseload by status') }}</h2>

                        @include('partials.status-distribution', [
                            'counts' => $activeByStatus,
                            'total' => $activeCount,
                        ])
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
