@extends('layouts.app')

@section('title', __('Import Cases'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-9">

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            <div class="card">
                <div class="card-header">{{ __('Import Cases') }}</div>

                <div class="card-body">
                    <p>{{ __('Bring in an existing caseload from a CSV or XLSX file. Nothing is created yet — the next step shows what would be created and what would be rejected, before anything is written.') }}</p>

                    <form method="POST" action="{{ route('cases.import.preview') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="file" class="form-label">{{ __('File') }}</label>
                            <input id="file" type="file" class="form-control @error('file') is-invalid @enderror" name="file" accept=".csv,.txt,.xlsx" required>
                            @error('file')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                            <div class="form-text">{{ __('CSV or XLSX, up to 5 MB.') }}</div>
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('Preview Import') }}</button>
                        <a href="{{ route('cases.index') }}" class="btn btn-link">{{ __('Cancel') }}</a>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">{{ __('Column contract') }}</div>

                <div class="card-body">
                    <p class="text-muted small">{{ __('First row must be a header row using these labels. A case may span several rows: repeat the case columns on each row and share the same Docket No. to add more than one victim or respondent.') }}</p>

                    <dl class="row small mb-0">
                        <dt class="col-sm-4">{{ __('Required') }}</dt>
                        <dd class="col-sm-8">Docket No., Title, Date of Docket</dd>

                        <dt class="col-sm-4">{{ __('Case detail') }}</dt>
                        <dd class="col-sm-8">Status, Investigator, Complexity Weight, Incident Details, Source of Information</dd>

                        <dt class="col-sm-4">{{ __('Timeline') }}</dt>
                        <dd class="col-sm-8">30-day Deadline, ROP Submitted, 120-day Deadline, FIR Submitted, Date Submitted To</dd>

                        <dt class="col-sm-4">{{ __('People (per row)') }}</dt>
                        <dd class="col-sm-8 mb-0">Victim Name/Age/Status/Sector, Respondent Name/Age/Status/Sector, Complainant Name</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
