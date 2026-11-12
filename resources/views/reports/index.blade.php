@extends('layouts.app')

@section('title', __('CHR monitoring worksheet'))

@php
    $user = Auth::user();
    $officeWide = $user->isSupervisor();
@endphp

@section('content')
{{-- Wider than the app's default shell: this page is a table first, and
     .container's 1320px cap left a third of a wide screen empty. --}}
<div class="container-fluid container-wide">
    <div class="row justify-content-center">
        <div class="col-12">
            @include('reports.partials.navigation')

            {{-- Same identity bar and stat tiles as the dashboards and /alerts:
                 a report is a fourth view of the same caseload, not a different
                 product. The tiles describe the filtered set, so they change
                 with the filter and always match the table below. --}}
            <div class="identity-bar mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h1 class="identity-bar__title">
                            <span class="identity-bar__name">
                                {{ __('CHR monitoring worksheet') }}
                            </span>
                        </h1>

                        <div class="identity-bar__meta">
                            {{ $user->office_region }} &middot; {{ $officeWide ? __('Office-wide') : __('My cases') }}
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('reports.export-excel', $filters) }}" class="btn btn-sm btn-primary">{{ __('Download CHR Excel') }}</a>
                        {{-- The filters are already in the URL, so the download
                             is the same request with a different ending. --}}
                        <a href="{{ route('reports.export', $filters) }}" class="btn btn-sm btn-outline-primary">
                            {{ __('Download CSV') }}
                        </a>
                    </div>
                </div>

                <div class="identity-bar__divider"></div>

                <div class="row row-cols-2 row-cols-lg-3 g-3">
                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'folder'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Cases in report') }}</span>
                                <span class="stat-tile__value">{{ $caseCount }}</span>
                                <span class="stat-tile__note">{{ $filters === [] ? __('no filter applied') : __('matching the filter') }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'layers'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Total complexity') }}</span>
                                <span class="stat-tile__value">{{ $weightTotal }}</span>
                            </span>
                        </div>
                    </div>

                    {{-- Only ever shown when a date range shut cases out. It is a
                         caveat on the figure beside it, not a standing metric. --}}
                    @if ($excludedCount > 0)
                        <div class="col">
                            <div class="stat-tile">
                                <span class="stat-tile__icon">@include('partials.icon', ['name' => 'alert-triangle'])</span>
                                <span>
                                    <span class="stat-tile__label">{{ __('Not counted') }}</span>
                                    <span class="stat-tile__value">{{ $excludedCount }}</span>
                                    <span class="stat-tile__note">{{ __('no timeline recorded') }}</span>
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <p>{{ __('Review the CHR monitoring fields below, or download every matching case as an Excel workbook with one worksheet per investigator. The table is paginated; downloads include all matching cases.') }}</p>
            <details class="record-help mb-3 small">
                <summary>{{ __('About downloads and re-importing') }}</summary>
                <p class="mt-2 mb-0">{{ __('CHR Excel follows the office template, including extra rows for people and recorded submission dates. It omits CaseTrack-only fields such as progress status and complexity and is not a complete backup. Import reads the active worksheet only, previews new cases, and rejects existing docket numbers. The simpler CSV download retains its existing eleven columns.') }}</p>
            </details>
            @include('cases.partials.record-label-help')

            <div class="card filter-panel mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('reports.index') }}">
                        <div class="filter-fields">
                            <div class="filter-field">
                                <label for="from" class="form-label">{{ __('Docketed from') }}</label>
                                {{-- Native date input: the browser supplies the
                                     picker, the locale and the keyboard support. --}}
                                <input type="date" class="form-control @error('from') is-invalid @enderror"
                                       id="from" name="from" value="{{ old('from', $filters['from'] ?? '') }}">
                                @error('from')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="filter-field">
                                <label for="to" class="form-label">{{ __('Docketed to') }}</label>
                                <input type="date" class="form-control @error('to') is-invalid @enderror"
                                       id="to" name="to" value="{{ old('to', $filters['to'] ?? '') }}">
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
                                                @selected(old('investigator_id', $filters['investigator_id'] ?? null) == $investigator->id)>
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
                                {{-- Free text, not a select. cases.status has no
                                     ratified vocabulary yet, so a fixed list of
                                     options here would invent one. --}}
                                <input type="text" class="form-control @error('status') is-invalid @enderror"
                                       id="status" name="status" value="{{ old('status', $filters['status'] ?? '') }}"
                                       list="report-status-options" autocomplete="off">
                                <datalist id="report-status-options">
                                    @foreach ($byStatus->keys() as $status)
                                        <option value="{{ $status }}"></option>
                                    @endforeach
                                </datalist>
                                @error('status')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            {{-- Clear then Apply, with Apply furthest right: the
                                 form reads left to right and ends on the action
                                 that submits it. --}}
                            <div class="filter-actions">
                                @if ($filters !== [])
                                    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
                                        {{ __('Clear') }}
                                    </a>
                                @endif
                                <button type="submit" class="btn btn-primary">{{ __('Apply') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @if ($caseCount > 0)
                <div class="card mb-3">
                    <div class="card-header">
                        <h2 class="h6 mb-0">{{ __('Status mix') }}</h2>
                    </div>
                    <div class="card-body">
                        {{-- The distribution of what the report covers, closed
                             cases included — unlike the dashboards, which show
                             the active mix only. A report over a period that
                             hid the work finished in it would misreport it. --}}
                        @include('partials.status-distribution', ['counts' => $byStatus, 'total' => $caseCount])
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">{{ __('Monitoring records') }}</h2>
                    <span class="badge badge-soft-blue">{{ $caseCount }}</span>
                </div>

                <div class="card-body">
                    @if ($rows->isEmpty())
                        <p class="mb-0">{{ __('No cases match this filter.') }}</p>
                    @else
                        @include('reports.partials.worksheet')
                        {{ $rows->links() }}
                    @endif

                    {{-- The date range reads through to case_timelines, so a case
                         with no timeline row cannot satisfy it. Reported rather
                         than dropped: a report whose total quietly shrank is
                         worse than one carrying a caveat. --}}
                    @if ($excludedCount > 0)
                        <p class="text-muted small mb-0 mt-3">
                            {{ trans_choice(
                                '{1} :n case is excluded by the date range because it has no timeline recorded.|[2,*] :n cases are excluded by the date range because they have no timeline recorded.',
                                $excludedCount,
                                ['n' => $excludedCount]
                            ) }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
