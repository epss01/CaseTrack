@extends('layouts.app')

@section('title', __('Edit case').' · '.$case->docket_no)

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header"><h1 class="h5 mb-0">{{ __('Edit case') }} &mdash; {{ $case->docket_no }}</h1></div>

                <div class="card-body">
                    @if (request()->query('classification') === 'unknown')
                        <p><a href="{{ route('cases.index', ['classification' => 'unknown']) }}">{{ __('Return to classification review without saving') }}</a></p>
                    @endif
                    <p><a href="{{ route('cases.timeline.edit', $case) }}">{{ __('Edit timeline dates separately') }}</a></p>
                    <form method="POST" action="{{ route('cases.update', request()->query('classification') === 'unknown' ? [$case, 'classification' => 'unknown'] : $case) }}">
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

                        <fieldset class="mt-4">
                            <legend class="h6">{{ __('Case classification') }}</legend>
                            <label for="is_torture_case" class="form-label">{{ __('Torture case') }}</label>
                            <select id="is_torture_case" name="is_torture_case" class="form-select @error('is_torture_case') is-invalid @enderror" aria-describedby="classification-help">
                                <option value="" @selected(old('is_torture_case', $case->is_torture_case) === null || old('is_torture_case', $case->is_torture_case) === '')>{{ __('Unknown') }}</option>
                                <option value="1" @selected((string) old('is_torture_case', $case->is_torture_case) === '1')>{{ __('Yes') }}</option>
                                <option value="0" @selected(in_array(old('is_torture_case', $case->is_torture_case), [false, 0, '0'], true))>{{ __('No') }}</option>
                            </select>
                            <p id="classification-help" class="form-text">{{ __('Keep Unknown until the classification is confirmed. The 60-day deadline is evaluated only for confirmed torture cases. Clear any recorded external extension in the timeline editor before changing to No or Unknown.') }}</p>
                            @error('is_torture_case')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
                        </fieldset>
                        @include('cases.partials.edit-people')

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
                                    {{ request()->query('classification') === 'unknown' ? __('Save and return to review') : __('Save Changes') }}
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

@push('scripts')
    @include('cases.partials.people-script', ['editing' => true])
@endpush
