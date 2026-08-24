@extends('layouts.app')

@section('title', __('Import Preview'))

@php
    $readyCount = count($result['ready']);
    $errorCount = count($result['errors']);
@endphp

@section('content')
<div class="container-fluid container-wide">
    <div class="row justify-content-center">
        <div class="col-12">

            <div class="card mb-3">
                <div class="card-header">{{ __('Import Preview') }}</div>

                <div class="card-body">
                    <p class="mb-2">
                        {{ __(':file — :ready case(s) ready to create, :errors row(s) rejected.', [
                            'file' => $fileName,
                            'ready' => $readyCount,
                            'errors' => $errorCount,
                        ]) }}
                    </p>

                    @if ($result['defaulted_weight'] > 0)
                        <p class="text-muted small mb-3">
                            {{ trans_choice(
                                ':count case had no valid Complexity Weight and defaulted to 3 — review it after import.|:count cases had no valid Complexity Weight and defaulted to 3 — review them after import.',
                                $result['defaulted_weight'],
                                ['count' => $result['defaulted_weight']]
                            ) }}
                        </p>
                    @endif

                    <p class="text-muted small">{{ __('Nothing has been written yet. Confirm below to create the ready cases; rejected rows are not imported and must be fixed in the file and re-uploaded.') }}</p>

                    <form method="POST" action="{{ route('cases.import.store') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary" @if ($readyCount === 0) disabled @endif>
                            {{ __('Confirm Import') }} ({{ $readyCount }})
                        </button>
                    </form>
                    <a href="{{ route('cases.import.create') }}" class="btn btn-link">{{ __('Upload a different file') }}</a>
                </div>
            </div>

            @if ($readyCount > 0)
                <div class="card mb-3">
                    <div class="card-header">{{ __('Ready to create') }} ({{ $readyCount }})</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Docket No.') }}</th>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Investigator') }}</th>
                                    <th>{{ __('Complexity') }}</th>
                                    <th>{{ __('Date of Docket') }}</th>
                                    <th>{{ __('Victims') }}</th>
                                    <th>{{ __('Respondents') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($result['ready'] as $row)
                                    <tr>
                                        <td>{{ $row['docket_no'] }}</td>
                                        <td>{{ $row['case_title'] }}</td>
                                        <td>{{ $row['status'] }}</td>
                                        <td>{{ $investigatorNames[$row['investigator_id']] ?? '—' }}</td>
                                        <td>{{ $row['complexity_weight'] }}</td>
                                        <td>{{ $row['date_of_docket'] }}</td>
                                        <td>{{ count($row['victims']) }}</td>
                                        <td>{{ count($row['respondents']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($errorCount > 0)
                <div class="card border-danger mb-3">
                    <div class="card-header bg-danger text-white">{{ __('Rejected') }} ({{ $errorCount }})</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Docket No.') }}</th>
                                    <th>{{ __('Line(s)') }}</th>
                                    <th>{{ __('Reason') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($result['errors'] as $error)
                                    <tr>
                                        <td>{{ $error['docket_no'] ?? '—' }}</td>
                                        <td>{{ implode(', ', $error['lines']) }}</td>
                                        <td>
                                            @foreach ($error['messages'] as $message)
                                                <div>{{ $message }}</div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection
