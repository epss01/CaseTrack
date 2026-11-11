@extends('layouts.app')

@section('title', __('Reassign case'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header"><h1 class="h5 mb-0">{{ __('Reassign Case') }} &mdash; {{ $case->docket_no }}</h1></div>

                <div class="card-body">
                    <form method="POST" action="{{ route('cases.reassign.update', $case) }}">
                        @csrf
                        @method('PUT')

                        <fieldset>
                            <legend class="h6 mb-3">{{ __('Assignment and docket number') }}</legend>
                        <div class="mb-3">
                            <label for="investigator_id" class="form-label">{{ __('Assign To') }} <span class="text-muted small">({{ __('required') }})</span></label>

                            <div class="w-100">
                                <select id="investigator_id" class="form-select @error('investigator_id') is-invalid @enderror" name="investigator_id" required autofocus>
                                    @include('cases.partials.investigator-options', ['selected' => old('investigator_id', $case->investigator_id)])
                                </select>

                                <span class="form-text">{{ __('Ordered by Workload Capacity Score — least loaded first.') }}</span>

                                @error('investigator_id')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="docket_no" class="form-label">{{ __('Docket No.') }} <span class="text-muted small">({{ __('required') }})</span></label>

                            <div class="w-100">
                                <input id="docket_no" type="text" class="form-control @error('docket_no') is-invalid @enderror" name="docket_no" value="{{ old('docket_no', $case->docket_no) }}" required>

                                <span class="form-text">{{ __('Only change this to correct a mis-entered docket number.') }}</span>

                                @error('docket_no')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        </fieldset>
                        <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Save Reassignment') }}
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
