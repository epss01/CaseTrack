@extends('layouts.app')

@section('title', $case->docket_no)

@php
    $timeline = $case->timeline;

    $timelineFields = [
        __('Date of Docket') => $timeline?->date_of_docket?->format('d M Y'),
        __('Date of Submission (ROP)') => $timeline?->date_submission_rop?->format('d M Y'),
        __('30-Day Extension') => $timeline?->extension_30_days?->format('d M Y'),
        __('Submission (60th Day)') => $timeline?->submission_60th_day?->format('d M Y'),
        __('Submission (60th Day RORP)') => $timeline?->date_submission_60th_day_rorp?->format('d M Y'),
        __('Submission (120th Day)') => $timeline?->submission_120th_day?->format('d M Y'),
        __('Target Date (FIR)') => $timeline?->target_date_fir?->format('d M Y'),
        __('Date FIR Submitted') => $timeline?->date_fir_submitted?->format('d M Y'),
        __('Submitted To') => $timeline?->date_submitted_to,
    ];
@endphp

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-11 case-record">

            <a href="{{ route('cases.index') }}" class="d-inline-block mb-2">{{ __('Back to cases') }}</a>

            @if (session('status'))
                <div class="alert alert-success" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <p class="fw-semibold mb-1">{{ __('The case was not changed.') }}</p>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="page-heading">
                <div>
                    <h1>{{ $case->docket_no }}</h1>
                    <p class="mb-2">{{ $case->case_title }}</p>
                    @include('cases.partials.status-badge', ['status' => $case->status])
                    <span class="text-muted ms-2">{{ __('Assigned to: :name', ['name' => $case->investigator?->full_name ?? '—']) }}</span>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @can('update', $case)
                        <a href="{{ route('cases.edit', $case) }}" class="btn btn-outline-primary">{{ __('Edit case') }}</a>
                        <a href="{{ route('cases.timeline.edit', $case) }}" class="btn btn-outline-secondary">{{ __('Set timeline') }}</a>
                    @endcan

                    @can('changeClosure', $case)
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#caseClosure">
                            {{ $case->status === \App\Models\CaseModel::STATUS_CLOSED ? __('Reopen case') : __('Close case') }}
                        </button>
                    @endcan

                    {{-- Supervisor-only; investigators never see these. --}}
                    @can('reassign', $case)
                        <a href="{{ route('cases.reassign.edit', $case) }}" class="btn btn-outline-secondary">{{ __('Reassign') }}</a>
                    @endcan

                    @can('delete', $case)
                        <a href="{{ route('cases.confirm-delete', $case) }}" class="btn btn-outline-danger ms-sm-3">{{ __('Delete') }}</a>
                    @endcan
                </div>
            </div>

            <nav class="section-nav" aria-label="{{ __('Case sections') }}">
                <span class="text-muted">{{ __('Jump to:') }}</span>
                <a href="#case-details">{{ __('Details') }}</a>
                <a href="#case-people">{{ __('People') }}</a>
                <a href="#case-timeline">{{ __('Timeline') }}</a>
            </nav>

            <section class="card mb-4 case-section" id="case-details" tabindex="-1" aria-labelledby="details-heading">
                <div class="card-header"><h2 class="h6 mb-0" id="details-heading">{{ __('Case details') }}</h2></div>

                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">{{ __('Docket No.') }}</dt>
                        <dd class="col-sm-9">{{ $case->docket_no }}</dd>

                        <dt class="col-sm-3">{{ __('Case Title') }}</dt>
                        <dd class="col-sm-9">{{ $case->case_title }}</dd>

                        <dt class="col-sm-3">{{ __('Status') }}</dt>
                        <dd class="col-sm-9">{{ $case->status }}</dd>

                        <dt class="col-sm-3">{{ __('Investigator') }}</dt>
                        <dd class="col-sm-9">{{ $case->investigator?->full_name ?? '—' }}</dd>

                        <dt class="col-sm-3">{{ __('Office Region') }}</dt>
                        <dd class="col-sm-9">{{ $case->investigator?->office_region ?? '—' }}</dd>

                        <dt class="col-sm-3">{{ __('Source of Information') }}</dt>
                        <dd class="col-sm-9">{{ $case->source_info ?: '—' }}</dd>

                        <dt class="col-sm-3">{{ __('Complexity Weight') }}</dt>
                        <dd class="col-sm-9">{{ $case->complexity_weight }}</dd>

                        {{-- Null for any case docketed before this field existed: shown as "Not
                             determined" rather than "No" so an unknown never reads as a fact. --}}
                        <dt class="col-sm-3">{{ __('Torture Case') }}</dt>
                        <dd class="col-sm-9">
                            {{ is_null($case->is_torture_case) ? __('Not determined') : ($case->is_torture_case ? __('Yes') : __('No')) }}
                        </dd>

                        {{-- Record creation, distinct from the official Date of Docket below. --}}
                        <dt class="col-sm-3">{{ __('Date Encoded') }}</dt>
                        <dd class="col-sm-9">{{ $case->created_at?->format('d M Y') ?? '—' }}</dd>

                        <dt class="col-sm-3">{{ __('Incident Details') }}</dt>
                        <dd class="col-sm-9 mb-0">{{ $case->incident_details }}</dd>
                    </dl>
                </div>
            </section>

            <section class="case-section" id="case-people" tabindex="-1" aria-labelledby="people-heading">
            <h2 class="h5 mb-3" id="people-heading">{{ __('People') }}</h2>
            <div class="card mb-4">
                <div class="card-header"><h3 class="h6 mb-0">{{ __('Complainants') }} <span class="text-muted">({{ $case->complainants->count() }})</span></h3></div>

                <div class="card-body">
                    @if ($case->complainants->isEmpty())
                        <p class="text-muted mb-0">{{ __('No complainants recorded.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Name') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($case->complainants as $complainant)
                                        <tr>
                                            <td>{{ $complainant->name }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h3 class="h6 mb-0">{{ __('Victims') }} <span class="text-muted">({{ $case->victims->count() }})</span></h3></div>

                <div class="card-body">
                    @if ($case->victims->isEmpty())
                        <p class="text-muted mb-0">{{ __('No victims recorded.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Name') }}</th>
                                        <th scope="col">{{ __('Age') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Sector') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($case->victims as $victim)
                                        <tr>
                                            <td>{{ $victim->name }}</td>
                                            <td>{{ $victim->age ?? '—' }}</td>
                                            <td>{{ $victim->status }}</td>
                                            <td>{{ $victim->sector }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h3 class="h6 mb-0">{{ __('Respondents') }} <span class="text-muted">({{ $case->respondents->count() }})</span></h3></div>

                <div class="card-body">
                    @if ($case->respondents->isEmpty())
                        <p class="text-muted mb-0">{{ __('No respondents recorded.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Name') }}</th>
                                        <th scope="col">{{ __('Age') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Sector') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($case->respondents as $respondent)
                                        <tr>
                                            <td>{{ $respondent->name }}</td>
                                            <td>{{ $respondent->age ?? '—' }}</td>
                                            <td>{{ $respondent->status ?: '—' }}</td>
                                            <td>{{ $respondent->sector ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            </section>

            <section class="card mb-4 case-section" id="case-timeline" tabindex="-1" aria-labelledby="timeline-heading">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0" id="timeline-heading">{{ __('Timeline') }}</h2>

                    @can('update', $case)
                        <a href="{{ route('cases.timeline.edit', $case) }}" class="btn btn-sm btn-outline-primary">
                            {{ __('Set Timeline') }}
                        </a>
                    @endcan
                </div>

                <div class="card-body">
                    {{-- Without a timeline row every label would render as a
                         wall of dashes, which reads as broken rather than empty. --}}
                    @if ($timeline)
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    @foreach ($timelineFields as $label => $value)
                                        <tr>
                                            <th scope="row" class="w-50 fw-normal text-muted">{{ $label }}</th>
                                            <td>{{ $value ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">{{ __('No timeline has been recorded for this case yet.') }}</p>
                    @endif
                </div>
            </section>

            <p><a href="{{ route('cases.index') }}">{{ __('Back to cases') }}</a></p>
        </div>
    </div>
</div>

@can('changeClosure', $case)
    @include('cases.partials.closure-dialog')
@endcan
@endsection
