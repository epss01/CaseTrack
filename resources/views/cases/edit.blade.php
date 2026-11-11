@extends('layouts.app')

@section('title', __('Edit case').' · '.$case->docket_no)

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header"><h1 class="h5 mb-0">{{ __('Edit case') }} &mdash; {{ $case->docket_no }}</h1></div>

                <div class="card-body">
                    <form method="POST" action="{{ route('cases.update', $case) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="lock_version" value="{{ old('lock_version', $case->lock_version) }}">

                        @error('lock_version')
                            <div class="alert alert-warning" role="alert">
                                {{ $message }}
                            </div>
                        @enderror

                        <fieldset>
                            <legend class="h6 mb-3">{{ __('Identification and status') }}</legend>
                        <div class="mb-3">
                            <label for="case_title" class="form-label">{{ __('Case Title') }} <span class="text-muted small">({{ __('required') }})</span></label>

                            <div class="w-100">
                                <input id="case_title" type="text" class="form-control @error('case_title') is-invalid @enderror" name="case_title" value="{{ old('case_title', $case->case_title) }}" required autofocus>

                                @error('case_title')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">{{ __('Status') }} <span class="text-muted small">({{ __('required') }})</span></label>

                            <div class="w-100">
                                <select id="status" class="form-select @error('status') is-invalid @enderror" name="status" required>
                                    @include('cases.partials.status-options', ['case' => $case, 'selected' => old('status', $case->status)])
                                </select>

                                @can('changeClosure', $case)
                                    <div class="form-text">{{ __('To close or reopen this case, use the dedicated button on case details.') }}</div>
                                @endcan

                                @error('status')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="source_info" class="form-label">{{ __('Source of Information') }} <span class="text-muted small">({{ __('optional') }})</span></label>

                            <div class="w-100">
                                <input id="source_info" type="text" class="form-control @error('source_info') is-invalid @enderror" name="source_info" value="{{ old('source_info', $case->source_info) }}">

                                @error('source_info')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        </fieldset>
                        <fieldset>
                            <legend class="h6 mt-4 mb-3">{{ __('Complexity and incident') }}</legend>
                        <div class="mb-3">
                            <label for="complexity_weight" class="form-label">{{ __('Complexity Weight') }} <span class="text-muted small">({{ __('required') }})</span></label>

                            <div class="w-100">
                                <select id="complexity_weight" class="form-select @error('complexity_weight') is-invalid @enderror" name="complexity_weight" required>
                                    @include('cases.partials.complexity-options', ['selected' => old('complexity_weight', $case->complexity_weight)])
                                </select>

                                @error('complexity_weight')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="incident_details" class="form-label">{{ __('Incident Details') }} <span class="text-muted small">({{ __('required') }})</span></label>

                            <div class="w-100">
                                <textarea id="incident_details" rows="6" class="form-control @error('incident_details') is-invalid @enderror" name="incident_details" required>{{ old('incident_details', $case->incident_details) }}</textarea>

                                @error('incident_details')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        </fieldset>

                        @if ($onBehalf)
                            <fieldset class="mt-4">
                                <legend class="h6 mb-3">{{ __('Edit reason') }}</legend>
                            <div class="mb-3">
                                <label for="reason" class="form-label">{{ __('Reason for editing this case') }} <span class="text-muted small">({{ __('required') }})</span></label>

                                <div class="w-100">
                                    <textarea id="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" name="reason" required>{{ old('reason') }}</textarea>
                                    <div class="form-text">{{ __('You are not the assigned investigator on this case. State why you\'re editing it (e.g. covering leave, handover) — this is recorded in the audit trail.') }}</div>

                                    @error('reason')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            </fieldset>
                        @endif

                        <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Save Changes') }}
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
