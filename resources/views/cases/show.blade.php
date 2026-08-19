@extends('layouts.app')

@php
    $timeline = $case->timeline;

    $closurePending = $case->status === \App\Models\CaseModel::STATUS_PENDING_CLOSURE;
    $closureProposable = ! $closurePending && $case->status !== \App\Models\CaseModel::STATUS_CLOSED;

    $timelineFields = [
        __('Date of Docket') => $timeline?->date_of_docket?->format('d M Y'),
        __('Date of Submission (ROP)') => $timeline?->date_submission_rop?->format('d M Y'),
        __('30-Day Extension') => $timeline?->extension_30_days?->format('d M Y'),
        __('Submission (60th Day)') => $timeline?->submission_60th_day?->format('d M Y'),
        __('Submission (120th Day)') => $timeline?->submission_120th_day?->format('d M Y'),
        __('Target Date (FIR)') => $timeline?->target_date_fir?->format('d M Y'),
        __('Date FIR Submitted') => $timeline?->date_fir_submitted?->format('d M Y'),
        __('Submitted To') => $timeline?->date_submitted_to,
    ];
@endphp

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">

            @if (session('status'))
                <div class="alert alert-success" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="mb-0">{{ __('Case Profile Matrix') }}</h4>
                    <span class="text-muted">{{ $case->docket_no }}</span>
                </div>

                <div class="d-flex gap-2">
                    @can('update', $case)
                        <a href="{{ route('cases.edit', $case) }}" class="btn btn-primary">{{ __('Edit') }}</a>
                    @endcan

                    {{-- Maker: asks for the case to be closed, and nothing more. --}}
                    @can('proposeClosure', $case)
                        @if ($closureProposable)
                            <form method="POST" action="{{ route('cases.closure.propose', $case) }}">
                                @csrf
                                @method('PUT')

                                <button type="submit" class="btn btn-outline-success">{{ __('Propose Closure') }}</button>
                            </form>
                        @endif
                    @endcan

                    {{-- Checker: only a supervisor, and only once one is pending. --}}
                    @can('resolveClosure', $case)
                        @if ($closurePending)
                            <a href="{{ route('cases.closure.review', $case) }}" class="btn btn-warning">{{ __('Review Closure') }}</a>
                        @endif
                    @endcan

                    {{-- Supervisor-only; investigators never see these. --}}
                    @can('reassign', $case)
                        <a href="{{ route('cases.reassign.edit', $case) }}" class="btn btn-outline-secondary">{{ __('Reassign') }}</a>
                    @endcan

                    @can('delete', $case)
                        <a href="{{ route('cases.confirm-delete', $case) }}" class="btn btn-outline-danger">{{ __('Delete') }}</a>
                    @endcan
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">{{ __('Case Details') }}</div>

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
            </div>

            <div class="card mb-4">
                <div class="card-header">{{ __('Complainants') }} <span class="text-muted">({{ $case->complainants->count() }})</span></div>

                <div class="card-body">
                    @if ($case->complainants->isEmpty())
                        <p class="text-muted mb-0">{{ __('No complainants recorded.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
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
                <div class="card-header">{{ __('Victims') }} <span class="text-muted">({{ $case->victims->count() }})</span></div>

                <div class="card-body">
                    @if ($case->victims->isEmpty())
                        <p class="text-muted mb-0">{{ __('No victims recorded.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Age') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Sector') }}</th>
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
                <div class="card-header">{{ __('Respondents') }} <span class="text-muted">({{ $case->respondents->count() }})</span></div>

                <div class="card-body">
                    @if ($case->respondents->isEmpty())
                        <p class="text-muted mb-0">{{ __('No respondents recorded.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Age') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Sector') }}</th>
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

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Timeline') }}</span>

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
                                            <th class="w-50 fw-normal text-muted">{{ $label }}</th>
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
            </div>

            <p><a href="{{ route('cases.index') }}">{{ __('Back to cases') }}</a></p>
        </div>
    </div>
</div>
@endsection
