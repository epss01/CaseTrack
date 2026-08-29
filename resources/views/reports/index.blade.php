@extends('layouts.app')

@section('title', __('Reports'))

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

            {{-- Same identity bar and stat tiles as the dashboards and /alerts:
                 a report is a fourth view of the same caseload, not a different
                 product. The tiles describe the filtered set, so they change
                 with the filter and always match the table below. --}}
            <div class="identity-bar mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h1 class="identity-bar__title">
                            <span class="identity-bar__eyebrow">{{ __('Reports') }}</span>
                            <span class="identity-bar__name">
                                {{ $officeWide ? __('Office-wide') : __('My cases') }}
                                <span class="role-pill">{{ $user->role?->role_name }}</span>
                            </span>
                        </h1>

                        <div class="identity-bar__meta">
                            {{ $user->office_region }} &middot; {{ now()->translatedFormat('l, d F Y') }}
                        </div>
                    </div>

                    {{-- A link rather than a second submit button: the filters are
                         already in the URL, so the download is the same request
                         with a different ending. --}}
                    <a href="{{ route('reports.export', $filters) }}" class="btn btn-sm btn-outline-light">
                        {{ __('Download CSV') }}
                    </a>
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

            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="h6 mb-0">{{ __('Filter') }}</h2>
                </div>

                <div class="card-body">
                    <form method="GET" action="{{ route('reports.index') }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-sm-6 col-lg-3">
                                <label for="from" class="form-label">{{ __('Docketed from') }}</label>
                                {{-- Native date input: the browser supplies the
                                     picker, the locale and the keyboard support. --}}
                                <input type="date" class="form-control @error('from') is-invalid @enderror"
                                       id="from" name="from" value="{{ $filters['from'] ?? '' }}">
                                @error('from')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <label for="to" class="form-label">{{ __('Docketed to') }}</label>
                                <input type="date" class="form-control @error('to') is-invalid @enderror"
                                       id="to" name="to" value="{{ $filters['to'] ?? '' }}">
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
                                {{-- Free text, not a select. cases.status has no
                                     ratified vocabulary yet, so a fixed list of
                                     options here would invent one. --}}
                                <input type="text" class="form-control @error('status') is-invalid @enderror"
                                       id="status" name="status" value="{{ $filters['status'] ?? '' }}"
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
                            <div class="col-12 d-flex gap-2 justify-content-end">
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
                    <h2 class="h6 mb-0">{{ __('Case listing') }}</h2>
                    <span class="badge badge-soft-blue">{{ $caseCount }}</span>
                </div>

                <div class="card-body">
                    @if ($rows->isEmpty())
                        <p class="mb-0">{{ __('No cases match this filter.') }}</p>
                    @else
                        {{-- The listing shows the columns that carry information at a
                             readable width; the CSV carries all eleven. Office Region is
                             the same value on every row of a single-region deployment and
                             is already in the identity bar above, and each deadline is
                             paired with the submission that discharges it rather than
                             given a column of its own. Eleven separate columns needed
                             1281px inside an 893px wrapper, which is what pushed the View
                             button out of reach and squeezed Title to 79px. --}}
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle table-data">
                                <caption class="visually-hidden">
                                    {{ __('Cases in this report, with their statutory deadlines and the submissions that discharge them. The full set of columns is in the CSV download.') }}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Docket No.') }}</th>
                                        <th scope="col" class="col-title">{{ __('Title') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Phase') }}</th>
                                        {{-- Shown only where there is more than one
                                             caseload in view, exactly as /alerts does. --}}
                                        @if ($officeWide)
                                            <th scope="col">{{ __('Investigator') }}</th>
                                        @endif
                                        <th scope="col">{{ __('Complexity') }}</th>
                                        <th scope="col">{{ __('Docketed') }}</th>
                                        <th scope="col">{{ __('30-day (ROP)') }}</th>
                                        <th scope="col">{{ __('120-day (FIR)') }}</th>
                                        <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        @php $fields = $row['fields']; @endphp
                                        <tr>
                                            {{-- text-nowrap only where breaking the value
                                                 costs more than the width does: identifiers,
                                                 dates and badges. Everything else wraps, so
                                                 the table answers to its container. --}}
                                            <td class="text-nowrap">{{ $fields['docket_no'] }}</td>
                                            <td class="col-title">{{ $fields['case_title'] }}</td>
                                            <td class="text-nowrap">
                                                @include('cases.partials.status-badge', ['status' => $fields['status']])
                                            </td>
                                            <td>{{ $row['case']->docket_phase ?? '—' }}</td>
                                            @if ($officeWide)
                                                <td>{{ $fields['investigator'] ?? '—' }}</td>
                                            @endif
                                            <td>{{ $fields['complexity_weight'] }}</td>
                                            <td class="text-nowrap">{{ $fields['date_of_docket'] ?: '—' }}</td>

                                            {{-- Deadline over the submission that discharges
                                                 it: one fact about the case, so one cell. --}}
                                            <td class="text-nowrap">
                                                {{ $fields['thirty_day_deadline'] ?: '—' }}
                                                <span class="cell-sub text-muted">
                                                    {{ $fields['rop_submitted']
                                                        ? __('filed :date', ['date' => $fields['rop_submitted']])
                                                        : __('not filed') }}
                                                </span>
                                            </td>
                                            <td class="text-nowrap">
                                                {{ $fields['hundred_twentieth_day_deadline'] ?: '—' }}
                                                <span class="cell-sub text-muted">
                                                    {{ $fields['fir_submitted']
                                                        ? __('filed :date', ['date' => $fields['fir_submitted']])
                                                        : __('not filed') }}
                                                </span>
                                            </td>

                                            <td class="text-end">
                                                {{-- Into this case's report, not its edit
                                                     profile: the listing is a report and the
                                                     row below it should be one too. The
                                                     report links on to the profile. --}}
                                                <a href="{{ route('reports.show', $row['case']) }}" class="btn btn-sm btn-outline-primary">
                                                    {{ __('View') }}<span class="visually-hidden"> {{ __('report for case') }} {{ $row['case']->docket_no }}</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $rows->links() }}

                        <p class="text-muted small mb-0 mt-3">
                            {{ __('Office region and the exact submission dates are included in the CSV download.') }}
                        </p>
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
