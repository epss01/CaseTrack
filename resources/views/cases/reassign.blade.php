@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">{{ __('Reassign Case') }} &mdash; {{ $case->docket_no }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('cases.reassign.update', $case) }}">
                        @csrf
                        @method('PUT')

                        <div class="row mb-3">
                            <label for="investigator_id" class="col-md-4 col-form-label text-md-end">{{ __('Assign To') }}</label>

                            <div class="col-md-6">
                                <select id="investigator_id" class="form-select @error('investigator_id') is-invalid @enderror" name="investigator_id" required autofocus>
                                    <option value="">{{ __('Select an investigator') }}</option>
                                    @foreach ($investigators as $investigator)
                                        <option value="{{ $investigator->id }}" @selected(old('investigator_id', $case->investigator_id) == $investigator->id)>
                                            {{ $investigator->full_name }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('investigator_id')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="docket_no" class="col-md-4 col-form-label text-md-end">{{ __('Docket No.') }}</label>

                            <div class="col-md-6">
                                <input id="docket_no" type="text" class="form-control @error('docket_no') is-invalid @enderror" name="docket_no" value="{{ old('docket_no', $case->docket_no) }}" required>

                                <span class="form-text">{{ __('Only change this to correct a mis-entered docket number.') }}</span>

                                @error('docket_no')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Save Reassignment') }}
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
