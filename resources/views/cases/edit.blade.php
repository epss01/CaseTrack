@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">{{ __('Edit Case') }} &mdash; {{ $case->docket_no }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('cases.update', $case) }}">
                        @csrf
                        @method('PUT')

                        <div class="row mb-3">
                            <label for="case_title" class="col-md-4 col-form-label text-md-end">{{ __('Case Title') }}</label>

                            <div class="col-md-6">
                                <input id="case_title" type="text" class="form-control @error('case_title') is-invalid @enderror" name="case_title" value="{{ old('case_title', $case->case_title) }}" required autofocus>

                                @error('case_title')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="status" class="col-md-4 col-form-label text-md-end">{{ __('Status') }}</label>

                            <div class="col-md-6">
                                <input id="status" type="text" class="form-control @error('status') is-invalid @enderror" name="status" value="{{ old('status', $case->status) }}" required>

                                @error('status')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="source_info" class="col-md-4 col-form-label text-md-end">{{ __('Source of Information') }}</label>

                            <div class="col-md-6">
                                <input id="source_info" type="text" class="form-control @error('source_info') is-invalid @enderror" name="source_info" value="{{ old('source_info', $case->source_info) }}">

                                @error('source_info')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="complexity_weight" class="col-md-4 col-form-label text-md-end">{{ __('Complexity Weight') }}</label>

                            <div class="col-md-6">
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

                        <div class="row mb-3">
                            <label for="incident_details" class="col-md-4 col-form-label text-md-end">{{ __('Incident Details') }}</label>

                            <div class="col-md-6">
                                <textarea id="incident_details" rows="6" class="form-control @error('incident_details') is-invalid @enderror" name="incident_details" required>{{ old('incident_details', $case->incident_details) }}</textarea>

                                @error('incident_details')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Save Changes') }}
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
