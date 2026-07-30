@extends('layouts.app')

@php
    $dateFields = [
        'date_of_docket' => __('Date of Docket'),
        'date_submission_rop' => __('Date of Submission (ROP)'),
        'extension_30_days' => __('30-Day Extension'),
        'submission_60th_day' => __('Submission (60th Day)'),
        'submission_120th_day' => __('Submission (120th Day)'),
        'target_date_fir' => __('Target Date (FIR)'),
        'date_fir_submitted' => __('Date FIR Submitted'),
    ];
@endphp

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">{{ __('Set Timeline') }} &mdash; {{ $case->docket_no }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('cases.timeline.update', $case) }}">
                        @csrf
                        @method('PUT')

                        @foreach ($dateFields as $field => $label)
                            <div class="row mb-3">
                                <label for="{{ $field }}" class="col-md-4 col-form-label text-md-end">
                                    {{ $label }}
                                    @unless ($field === 'date_of_docket')
                                        <span class="text-muted small">({{ __('optional') }})</span>
                                    @endunless
                                </label>

                                <div class="col-md-6">
                                    <input id="{{ $field }}"
                                           type="date"
                                           class="form-control @error($field) is-invalid @enderror"
                                           name="{{ $field }}"
                                           value="{{ old($field, $timeline?->{$field}?->format('Y-m-d')) }}"
                                           @if ($field === 'date_of_docket') required @endif>

                                    @error($field)
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        @endforeach

                        {{-- A recipient, not a date, despite the column name. --}}
                        <div class="row mb-3">
                            <label for="date_submitted_to" class="col-md-4 col-form-label text-md-end">
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

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Save Timeline') }}
                                </button>

                                <a href="{{ route('cases.show', $case) }}" class="btn btn-link">
                                    {{ __('Cancel') }}
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
