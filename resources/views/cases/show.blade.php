@extends('layouts.app')

@php
    $timeline = $case->timeline;

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

                @can('update', $case)
                    <a href="{{ route('cases.edit', $case) }}" class="btn btn-primary">{{ __('Edit') }}</a>
                @endcan
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

                        {{-- Record creation, distinct from the official Date of Docket below. --}}
                        <dt class="col-sm-3">{{ __('Date Encoded') }}</dt>
                        <dd class="col-sm-9">{{ $case->created_at?->format('d M Y') ?? '—' }}</dd>

                        <dt class="col-sm-3">{{ __('Incident Details') }}</dt>
                        <dd class="col-sm-9 mb-0">{{ $case->incident_details }}</dd>
                    </dl>
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
                <div class="card-header">{{ __('Timeline') }}</div>

                <div class="card-body">
                    @unless ($timeline)
                        <p class="text-muted">{{ __('No timeline has been recorded for this case yet.') }}</p>
                    @endunless

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
                </div>
            </div>

            <p><a href="{{ route('cases.index') }}">{{ __('Back to cases') }}</a></p>
        </div>
    </div>
</div>
@endsection
