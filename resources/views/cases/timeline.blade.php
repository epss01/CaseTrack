@extends('layouts.app')

@php
    $dateFields = [
        'date_of_docket' => __('Date of Docket'),
        'date_submission_rop' => __('Date of Submission (ROP)'),
        'extension_30_days' => __('30-Day Extension'),
        'submission_60th_day' => __('Submission (60th Day)'),
        'date_submission_60th_day_rorp' => __('Submission (60th Day RORP)'),
        'submission_120th_day' => __('Submission (120th Day)'),
        'target_date_fir' => __('Target Date (FIR)'),
        'date_fir_submitted' => __('Date FIR Submitted'),
        'date_extension_30_days_requested' => __('30-Day Extension Request Date'),
        'date_submission_120th_day_rop' => __('120th-Day ROP Submission Date'),
    ];
    $dateGroups = [
        __('Docket') => ['date_of_docket'],
        __('Deadlines and targets') => ['extension_30_days', 'submission_60th_day', 'submission_120th_day', 'target_date_fir'],
        __('Recorded submissions') => ['date_submission_rop', 'date_submission_60th_day_rorp', 'date_fir_submitted', 'date_extension_30_days_requested', 'date_submission_120th_day_rop'],
    ];
@endphp

@section('title', __('Set timeline'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header"><h1 class="h5 mb-0">{{ __('Set Timeline') }} &mdash; {{ $case->docket_no }}</h1></div>

                <div class="card-body">
                    <p><a href="{{ route('cases.edit', $case) }}">{{ __('Edit case details and people') }}</a></p>
                    <p id="timeline-help" class="text-muted">{{ __('Extension request and 120th-day ROP submission dates record what happened. They do not grant an extension, change a deadline, or mark the FIR submitted.') }}</p>
                    @include('cases.partials.torture-deadline')
                    <form method="POST" action="{{ route('cases.timeline.update', $case) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="lock_version" value="{{ old('lock_version', $case->lock_version) }}">
                        @error('lock_version')<div class="alert alert-warning" role="alert">{{ $message }}</div>@enderror

                        @if ($case->is_torture_case === true || $timeline?->extension_60th_days)
                            <fieldset class="mb-4">
                                <legend class="h6 float-none">{{ __('External torture-case extension') }}</legend>
                                <input type="hidden" name="torture_extension_granted" value="0">
                                <div class="form-check d-flex align-items-center gap-2 ps-0">
                                    <input class="form-check-input ms-0 mt-0 flex-shrink-0 @error('torture_extension_granted') is-invalid @enderror" id="torture_extension_granted" type="checkbox" name="torture_extension_granted" value="1"
                                           @checked(old('torture_extension_granted', $timeline?->extension_60th_days !== null)) aria-describedby="extension-help @error('torture_extension_granted') extension-error @enderror" @error('torture_extension_granted') aria-invalid="true" @enderror>
                                    <label class="form-check-label py-3" for="torture_extension_granted">{{ __('Externally granted 30-day extension') }}</label>
                                </div>
                                <p id="extension-help" class="form-text">{{ __('Record a grant made outside CaseTrack. A request alone does not qualify. Prototype assumption: a granted extension ends 90 calendar days from docketing, not 30 days from approval. Uncheck to remove a mistaken grant.') }}</p>
                                @error('torture_extension_granted')<p id="extension-error" class="text-danger" role="alert">{{ $message }}</p>@enderror
                                <label for="extension_note" class="form-label">{{ __('External approval reference or explanatory note') }}</label>
                                <textarea id="extension_note" name="extension_note" maxlength="500" rows="2" class="form-control @error('extension_note') is-invalid @enderror" aria-describedby="extension-note-help @error('extension_note') extension-note-error @enderror" @error('extension_note') aria-invalid="true" @enderror>{{ old('extension_note') }}</textarea>
                                <p id="extension-note-help" class="form-text">{{ __('Required when recording, removing or recalculating the extension, including a docket-date correction. Stored in the audit trail; do not enter case narratives here.') }}</p>
                                @error('extension_note')<p id="extension-note-error" class="text-danger" role="alert">{{ $message }}</p>@enderror
                            </fieldset>
                        @endif

                        @foreach ($dateGroups as $group => $fields)
                        <fieldset class="mb-4">
                            <legend class="h6 mb-3">{{ $group }}</legend>
                            <div class="row g-3">
                        @foreach ($fields as $field)
                            <div class="col-md-6">
                                <label for="{{ $field }}" class="form-label">
                                    {{ $dateFields[$field] }}
                                    <span class="text-muted small">({{ $field === 'date_of_docket' ? __('required') : __('optional') }})</span>
                                </label>

                                <div>
                                    <input id="{{ $field }}"
                                           type="date"
                                           class="form-control @error($field) is-invalid @enderror"
                                           name="{{ $field }}"
                                           aria-describedby="timeline-help @error($field) {{ $field }}-error @enderror"
                                           @error($field) aria-invalid="true" @enderror
                                           value="{{ old($field, $timeline?->{$field}?->format('Y-m-d')) }}"
                                           @if ($field === 'date_of_docket') required @endif>

                                    @error($field)
                                        <span id="{{ $field }}-error" class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        @endforeach
                            </div>
                        @if ($loop->last)
                        {{-- A recipient, not a date, despite the column name. --}}
                        <div class="mt-3">
                            <label for="date_submitted_to" class="form-label">
                                {{ __('Submitted To') }} <span class="text-muted small">({{ __('optional') }})</span>
                            </label>

                            <div class="col-md-6">
                                <input id="date_submitted_to"
                                       type="text"
                                       class="form-control @error('date_submitted_to') is-invalid @enderror"
                                       name="date_submitted_to"
                                       value="{{ old('date_submitted_to', $timeline?->date_submitted_to) }}">

                                @error('date_submitted_to')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        @endif
                        </fieldset>
                        @endforeach

                        @if (auth()->user()->isSupervisor())
                            <div class="mb-4">
                                <label for="reason" class="form-label">{{ __('Reason for editing this timeline') }} ({{ __('required') }})</label>
                                <textarea id="reason" name="reason" class="form-control @error('reason') is-invalid @enderror" maxlength="500" required rows="3">{{ old('reason') }}</textarea>
                                <p class="form-text">{{ __('This reason is recorded in the audit trail.') }}</p>
                                @error('reason')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>
                        @endif
                        <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Save Timeline') }}
                                </button>

                                <a href="{{ route('cases.show', $case) }}" class="btn btn-link">
                                    {{ __('Cancel') }}
                                </a>
                            </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
