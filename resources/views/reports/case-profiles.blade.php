@extends('layouts.app')

@section('title', __('Case profiles'))

@section('content')
<div class="container-fluid container-wide">
    @include('reports.partials.navigation')
    <div class="page-heading">
        <div>
            <h1>{{ __('Case profiles') }}</h1>
            <p class="text-muted mb-0">
                {{ auth()->user()->isSupervisor() ? __('Office-wide') : __('My cases') }}
                &middot; {{ __(':eligible of :submitted FIR submissions eligible', ['eligible' => number_format($report['eligible']), 'submitted' => number_format($report['submitted'])]) }}
            </p>
        </div>
    </div>
    <form method="GET" action="{{ route('reports.case-profiles') }}" class="card filter-panel mb-3">
        <fieldset class="card-body">
            <legend class="h6">{{ __('FIR submission period') }}</legend>
            <div class="filter-fields">
                @foreach (['from' => [__('From'), $from], 'to' => [__('To'), $to]] as $field => [$label, $date])
                    <div class="filter-field">
                        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                        <input type="date" name="{{ $field }}" id="{{ $field }}"
                               class="form-control @error($field) is-invalid @enderror"
                               value="{{ is_string(old($field)) ? old($field) : $date }}"
                               @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                        @error($field)
                            <span id="{{ $field }}-error" class="invalid-feedback" role="alert">{{ $message }}</span>
                        @enderror
                    </div>
                @endforeach
                <div class="filter-actions">
                    <a class="btn btn-outline-secondary" href="{{ route('reports.case-profiles') }}">{{ __('Clear') }}</a>
                    <button class="btn btn-primary" type="submit">{{ __('Apply') }}</button>
                </div>
            </div>
        </fieldset>
    </form>
    <section class="card mb-3">
        <div class="card-body">
            <h2>{{ __('Profile comparison') }}</h2>
            @if ($report['reason'])
                <p>{{ __($report['reason']) }}</p>
            @else
                <p>{{ __('Profiles are ordered by average days from docket to Final Investigation Report (FIR) submission. Profile numbers apply only to this selection.') }}</p>
            @endif
            @if ($report['cohort'] !== null)
                <div class="table-responsive" tabindex="0" role="region" aria-label="{{ __('Case profile comparison') }}">
                    <table class="table table-data align-middle mb-0" aria-label="{{ __('Profile averages compared with all eligible cases') }}">
                        <thead><tr>
                            @foreach ([__('Group'), __('Cases'), __('Share of eligible cases'), __('Mean complexity (1–5)'), __('Mean party records'), __('Mean days to FIR')] as $label)
                                <th scope="col">{{ $label }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                            @foreach ($report['profiles'] as $profile)
                                <tr>
                                    <th scope="row">{{ __('Profile :n', ['n' => $loop->iteration]) }}</th>
                                    <td>{{ number_format(count($profile['members'])) }}</td>
                                    <td>{{ number_format(100 * count($profile['members']) / $report['eligible'], 1) }}%</td>
                                    @foreach ($profile['means'] as $mean)
                                        <td>{{ number_format($mean, 1) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr>
                            <th scope="row">{{ __('All eligible cases') }}</th>
                            <td>{{ number_format($report['eligible']) }}</td>
                            <td>100%</td>
                            @foreach ($report['cohort'] as $mean)
                                <td>{{ number_format($mean, 1) }}</td>
                            @endforeach
                        </tr></tfoot>
                    </table>
                </div>
            @endif
            @if ($report['submitted'] > $report['eligible'])
                <h3 class="h6 mt-3">{{ __('Excluded submissions') }}</h3>
                <ul class="mb-0">
                    @foreach ($report['excluded'] as $reason => $count)
                        @if ($count)
                            <li>{{ __($reason) }} ({{ number_format($count) }})</li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
    @if ($report['profiles'])
        <section class="card mb-3">
            <div class="card-body">
                <h2>{{ __('Cases by profile') }}</h2>
                @foreach ($report['profiles'] as $profile)
                    <details class="calculation-details py-2">
                        <summary>{{ __('Profile :n · :count cases', ['n' => $loop->iteration, 'count' => number_format(count($profile['members']))]) }}</summary>
                        <div class="table-responsive" tabindex="0" role="region" aria-label="{{ __('Profile :n case details', ['n' => $loop->iteration]) }}">
                            <table class="table table-data align-middle mb-0" aria-label="{{ __('Cases in profile :n', ['n' => $loop->iteration]) }}">
                                <thead><tr>
                                    <th scope="col">{{ __('Docket number') }}</th>
                                    <th scope="col">{{ __('Complexity (1–5)') }}</th>
                                    <th scope="col">{{ __('Party records') }}</th>
                                    <th scope="col">{{ __('Days to FIR') }}</th>
                                </tr></thead>
                                <tbody>
                                    @foreach ($profile['members'] as $member)
                                        <tr>
                                            <th scope="row"><a href="{{ route('reports.show', $member['id']) }}">{{ $member['docket'] }}</a></th>
                                            @foreach ($member['values'] as $value)
                                                <td>{{ number_format($value) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @endif
    <details class="calculation-details">
        <summary>{{ __('How cases are grouped') }}</summary>
        <p class="mt-2">{{ __('K-means groups cases with similar complexity, party record counts and days from docket to FIR submission. Each measurement is standardized across the eligible cases so the three carry equal weight. A value shared by every case has no effect on grouping.') }}</p>
        <p>{{ __('The period uses the date of Final Investigation Report (FIR) submission, regardless of case status. Cases without a submission date cannot be placed in a period. Party records count each victim, respondent and complainant entry, including entries for the same person in different roles.') }}</p>
        <p class="mb-0">{{ __('Complexity and party counts reflect current records. Imports can substitute a default complexity when a source value is missing or invalid. The stored weight alone cannot identify which cases received that default.') }}</p>
    </details>
</div>
@endsection
