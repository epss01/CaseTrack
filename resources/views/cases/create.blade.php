@extends('layouts.app')

@php
    $blankRow = ['name' => '', 'age' => '', 'status' => '', 'sector' => ''];
    $blankComplainant = ['name' => ''];
    $victimRows = old('victims') ?: [$blankRow];
    $respondentRows = old('respondents') ?: [$blankRow];
    $complainantRows = old('complainants') ?: [$blankComplainant];
@endphp

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <form method="POST" action="{{ route('cases.store') }}">
                @csrf

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        {{ __('Please correct the highlighted fields before docketing this case.') }}
                    </div>
                @endif

                <div class="card mb-4">
                    <div class="card-header">{{ __('Case Intake') }}</div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="docket_no" class="form-label">{{ __('Docket No.') }}</label>
                                <input id="docket_no" type="text" class="form-control @error('docket_no') is-invalid @enderror" name="docket_no" value="{{ old('docket_no') }}" required autofocus>
                                @error('docket_no')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="date_of_docket" class="form-label">{{ __('Date of Docket') }}</label>
                                <input id="date_of_docket" type="date" class="form-control @error('date_of_docket') is-invalid @enderror" name="date_of_docket" value="{{ old('date_of_docket', now()->toDateString()) }}" required>
                                @error('date_of_docket')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="source_info" class="form-label">
                                    {{ __('Source of Information') }} <span class="text-muted small">({{ __('optional') }})</span>
                                </label>
                                <input id="source_info" type="text" class="form-control @error('source_info') is-invalid @enderror" name="source_info" value="{{ old('source_info') }}">
                                @error('source_info')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="case_title" class="form-label">{{ __('Case Title') }}</label>
                                <input id="case_title" type="text" class="form-control @error('case_title') is-invalid @enderror" name="case_title" value="{{ old('case_title') }}" required>
                                @error('case_title')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="incident_details" class="form-label">{{ __('Incident Details') }}</label>
                                <textarea id="incident_details" rows="6" class="form-control @error('incident_details') is-invalid @enderror" name="incident_details" required>{{ old('incident_details') }}</textarea>
                                @error('incident_details')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="complexity_weight" class="form-label">{{ __('Complexity Weight') }}</label>
                                <select id="complexity_weight" class="form-select @error('complexity_weight') is-invalid @enderror" name="complexity_weight" required>
                                    @include('cases.partials.complexity-options', ['selected' => old('complexity_weight')])
                                </select>
                                <span class="form-text">{{ __('Drives the Workload Capacity Score used to balance assignments.') }}</span>
                                @error('complexity_weight')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label d-block">{{ __('Torture Case') }}</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input @error('is_torture_case') is-invalid @enderror" type="radio" name="is_torture_case" id="is_torture_case_yes" value="1" @checked(old('is_torture_case') === '1') required>
                                    <label class="form-check-label" for="is_torture_case_yes">{{ __('Yes') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input @error('is_torture_case') is-invalid @enderror" type="radio" name="is_torture_case" id="is_torture_case_no" value="0" @checked(old('is_torture_case') === '0') required>
                                    <label class="form-check-label" for="is_torture_case_no">{{ __('No') }}</label>
                                </div>
                                <span class="form-text d-block">{{ __('Determines whether the 60th-day RORP milestone applies. Must be identified at docketing.') }}</span>
                                @error('is_torture_case')
                                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            @if ($investigators->isNotEmpty())
                                <div class="col-md-6">
                                    <label for="investigator_id" class="form-label">{{ __('Assign To') }}</label>
                                    <select id="investigator_id" class="form-select @error('investigator_id') is-invalid @enderror" name="investigator_id" required>
                                        @include('cases.partials.investigator-options', ['selected' => old('investigator_id')])
                                    </select>
                                    <span class="form-text">{{ __('Ordered by Workload Capacity Score — least loaded first.') }}</span>
                                    @error('investigator_id')
                                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            @else
                                <div class="col-12">
                                    <p class="text-muted mb-0">
                                        {{ __('This case will be assigned to you.') }}
                                    </p>
                                    @error('investigator_id')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            {{ __('Complainants') }}
                            <span class="text-muted small">({{ __('optional') }})</span>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-primary add-row" data-group="complainants">
                            {{ __('Add Complainant') }}
                        </button>
                    </div>

                    <div class="card-body">
                        @error('complainants')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror

                        <div data-rows="complainants">
                            @foreach ($complainantRows as $index => $row)
                                @include('cases.partials.complainant-row', [
                                    'index' => $index,
                                    'row' => $row,
                                ])
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ __('Victims') }}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary add-row" data-group="victims">
                            {{ __('Add Victim') }}
                        </button>
                    </div>

                    <div class="card-body">
                        @error('victims')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror

                        <div data-rows="victims">
                            @foreach ($victimRows as $index => $row)
                                @include('cases.partials.party-row', [
                                    'group' => 'victims',
                                    'index' => $index,
                                    'row' => $row,
                                    'optional' => false,
                                ])
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ __('Respondents') }}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary add-row" data-group="respondents">
                            {{ __('Add Respondent') }}
                        </button>
                    </div>

                    <div class="card-body">
                        @error('respondents')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror

                        <div data-rows="respondents">
                            @foreach ($respondentRows as $index => $row)
                                @include('cases.partials.party-row', [
                                    'group' => 'respondents',
                                    'index' => $index,
                                    'row' => $row,
                                    'optional' => true,
                                ])
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <button type="submit" class="btn btn-primary">{{ __('Docket Case') }}</button>
                    <a href="{{ route('cases.index') }}" class="btn btn-link">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="complainants-template">
    @include('cases.partials.complainant-row', [
        'index' => '__INDEX__',
        'row' => $blankComplainant,
    ])
</template>

<template id="victims-template">
    @include('cases.partials.party-row', [
        'group' => 'victims',
        'index' => '__INDEX__',
        'row' => $blankRow,
        'optional' => false,
    ])
</template>

<template id="respondents-template">
    @include('cases.partials.party-row', [
        'group' => 'respondents',
        'index' => '__INDEX__',
        'row' => $blankRow,
        'optional' => true,
    ])
</template>
@endsection

@push('scripts')
<script>
    document.addEventListener('click', function (event) {
        const addButton = event.target.closest('.add-row');

        if (addButton) {
            const group = addButton.dataset.group;
            const container = document.querySelector(`[data-rows="${group}"]`);
            const template = document.getElementById(`${group}-template`);

            // Keep indexes unique; gaps are fine, the server re-indexes.
            const nextIndex = Number(container.dataset.nextIndex || container.children.length);
            container.dataset.nextIndex = nextIndex + 1;

            container.insertAdjacentHTML(
                'beforeend',
                template.innerHTML.replaceAll('__INDEX__', nextIndex)
            );

            return;
        }

        const removeButton = event.target.closest('.remove-row');

        if (removeButton) {
            const row = removeButton.closest('.party-row');
            const container = row.parentElement;

            // Always leave one row so the form is never empty.
            if (container.querySelectorAll('.party-row').length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach((input) => {
                    input.value = '';
                });
            }
        }
    });
</script>
@endpush
