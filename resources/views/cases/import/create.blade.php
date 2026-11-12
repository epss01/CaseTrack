@extends('layouts.app')

@section('title', __('Import Cases'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="page-heading"><h1>{{ __('Import cases') }}</h1></div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    @include('cases.import.assignment-note')
                    <p>{{ __('Bring in an existing caseload from a CSV or XLSX file. Nothing is created yet — the next step shows what would be created and what would be rejected, before anything is written.') }}</p>
                    <p>{{ __('Import creates new cases only. Existing docket numbers are rejected, not updated. For a CHR Excel download, select and save the investigator worksheet you want as the active sheet before uploading; only that sheet is imported.') }}</p>
                    <p><a href="{{ route('reports.index') }}">{{ __('Open CHR monitoring worksheet and downloads') }}</a></p>

                    <form method="POST" action="{{ route('cases.import.preview') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="file" class="form-label">{{ __('File') }}</label>
                            <input id="file" type="file" class="form-control @error('file') is-invalid @enderror" name="file" accept=".csv,.txt,.xlsx" aria-describedby="file-help @error('file') file-error @enderror" @error('file') aria-invalid="true" @enderror required>
                            @error('file')
                                <span id="file-error" class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                            <div id="file-help" class="form-text">{{ __('CSV or XLSX, up to :size MB. Only the active worksheet is imported.', ['size' => \App\Http\Requests\UploadCaseImportRequest::MAX_SIZE_KB / 1024]) }}</div>
                        </div>

                        @if (auth()->user()->isSupervisor())
                            <div class="mb-3">
                                <label for="investigator_id" class="form-label">{{ __('Fallback investigator (optional)') }}</label>
                                <select id="investigator_id" name="investigator_id" class="form-select @error('investigator_id') is-invalid @enderror" aria-describedby="investigator-help @error('investigator_id') investigator-error @enderror" @error('investigator_id') aria-invalid="true" @enderror>
                                    <option value="">{{ __('Use investigator from file') }}</option>
                                    @foreach ($investigators as $investigator)
                                        <option value="{{ $investigator->id }}" @selected(old('investigator_id') == $investigator->id)>{{ $investigator->full_name }} ({{ $investigator->username }})</option>
                                    @endforeach
                                </select>
                                @error('investigator_id')
                                    <span id="investigator-error" class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                                <div id="investigator-help" class="form-text">{{ __('Used only when the file leaves the investigator blank. A name in the file must match an eligible account; this selection does not override it.') }}</div>
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary">{{ __('Preview Import') }}</button>
                        <a href="{{ route('cases.index') }}" class="btn btn-link">{{ __('Cancel') }}</a>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h2 class="h6 mb-0">{{ __('File columns') }}</h2></div>

                <div class="card-body">
                    <p>{{ __('CHR monitoring workbook: keep the title, investigator heading, and merged headers. Fill in the case rows below the headers. Repeat the docket number for additional people, or explicitly merge its cells across those rows.') }}</p>
                    <p>{{ __('Each case needs a docket number, title, docket date, incident details, at least one victim with status and sector, and at least one respondent. An investigator must be supplied in the file or selected above. Investigators importing their own cases may leave their name blank.') }}</p>
                    <p class="text-muted small">{{ __('Flat CSV/XLSX: use the labels below in the first row. Repeat the same Docket No. across rows to add more people to a case.') }}</p>

                    <dl class="row small mb-0">
                        <dt class="col-sm-4">{{ __('Required') }}</dt>
                        <dd class="col-sm-8">Docket No., Title, Date of Docket</dd>

                        <dt class="col-sm-4">{{ __('Case detail') }}</dt>
                        <dd class="col-sm-8">Status, Investigator, Complexity Weight, Incident Details, Source of Information</dd>

                        <dt class="col-sm-4">{{ __('Timeline') }}</dt>
                        <dd class="col-sm-8">30-day Deadline, ROP Submitted, 120-day Deadline, FIR Submitted, Date Submitted To, 30-day Extension Requested, 120th-day ROP Submitted, 60th-day RORP Submitted, Target Date of FIR</dd>

                        <dt class="col-sm-4">{{ __('People (per row)') }}</dt>
                        <dd class="col-sm-8 mb-0">Victim Name/Age/Status/Sector, Respondent Name/Age/Status/Sector, Complainant Name</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
