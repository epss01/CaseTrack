@extends('layouts.app')

@section('title', Auth::user()->isSupervisor() ? __('All Cases') : __('My Cases'))

@section('content')
{{-- Wider than the app's default shell, matching /alerts and /reports: this
     page is a table first, and .container's 1320px cap left a third of a
     wide screen empty. --}}
<div class="container-fluid container-wide">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="page-heading">
                <div>
                    <h1>{{ Auth::user()->isSupervisor() ? __('All cases') : __('My cases') }}</h1>
                    <p class="text-muted mb-0">{{ Auth::user()->isSupervisor() ? Auth::user()->office_region : __('Cases assigned to you') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @can('create', App\Models\CaseModel::class)
                        <a href="{{ route('cases.import.create') }}" class="btn btn-outline-primary">{{ __('Import cases') }}</a>
                    @endcan
                    @can('create', App\Models\CaseModel::class)
                        <a href="{{ route('cases.create') }}" class="btn btn-primary">{{ __('New case') }}</a>
                    @endcan
                </div>
            </div>
            <p class="small mb-2">{{ __('Unknown torture-case classifications in your authorized caseload: :active active / :total overall.', ['active' => $unknownActive, 'total' => $unknownTotal]) }}</p>
            <p class="small text-muted">{{ __('Preflight checks stored dates; it does not certify that every case has been classified.') }}</p>
            <div class="card filter-panel mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('cases.index') }}">
                        <input type="hidden" name="view" value="{{ $view }}">
                        <div class="filter-fields">
                            <div class="filter-field">
                                <label for="search" class="form-label">{{ __('Docket no., title, or party name') }}</label>
                                <input type="text" class="form-control @error('search') is-invalid @enderror"
                                       id="search" name="search" value="{{ $search ?? '' }}"
                                       autocomplete="off">
                                @error('search')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="filter-field d-flex align-items-end">
                                <div class="form-check d-flex align-items-center gap-2 ps-0">
                                    <input id="classification" class="form-check-input ms-0 mt-0 flex-shrink-0 @error('classification') is-invalid @enderror" type="checkbox" name="classification" value="unknown"
                                           @checked($classification === 'unknown') @error('classification') aria-invalid="true" aria-describedby="classification-error" @enderror>
                                    <label for="classification" class="form-check-label py-3">{{ __('Unknown classification only') }}</label>
                                </div>
                                @error('classification')<span id="classification-error" class="invalid-feedback d-block" role="alert">{{ $message }}</span>@enderror
                            </div>

                            <div class="filter-actions">
                                @if (filled($search))
                                    <a href="{{ route('cases.index', ['view' => $view, 'classification' => $classification]) }}" class="btn btn-outline-secondary">
                                        {{ __('Clear') }}
                                    </a>
                                @endif
                                <button type="submit" class="btn btn-primary">{{ __('Apply') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                        <p class="mb-0">{{ trans_choice('{1} :count case|[0,*] :count cases', $cases->total()) }}</p>
                        <nav class="btn-group" aria-label="{{ __('Case view') }}">
                            @foreach (['folders' => __('Folders'), 'list' => __('List')] as $mode => $label)
                                <a href="{{ route('cases.index', array_merge(request()->only('search', 'page', 'classification'), ['view' => $mode])) }}"
                                   class="btn {{ $view === $mode ? 'btn-primary' : 'btn-outline-primary' }}"
                                   @if ($view === $mode) aria-current="page" @endif>{{ $label }}</a>
                            @endforeach
                        </nav>
                    </div>
                    @if ($classification === 'unknown')
                        <p class="mb-3">{{ __('Active cases are shown first. Use Review classification to check the case records before choosing Yes or No. Otherwise keep Unknown; the 60-day deadline is not evaluated.') }}</p>
                    @endif
                    @include('cases.partials.record-label-help')
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($cases->isEmpty())
                        @if ($cases->total() > 0)
                            <p>{{ __('No cases on this page.') }} <a href="{{ route('cases.index', array_merge(request()->only('search', 'classification', 'view'), ['page' => 1])) }}">{{ __('Return to the first page') }}</a></p>
                        @else
                            <p class="mb-0">{{ $classification === 'unknown' ? (filled($search) ? __('No Unknown cases match this search.') : __('No Unknown cases to review.')) : (filled($search) ? __('No cases match this search.') : __('No cases to show.')) }}</p>
                        @endif
                    @else
                        @if ($view === 'folders')
                            <ul class="case-folders list-unstyled">
                                @foreach ($cases as $case)
                                    <li>
                                        <a class="case-folder" href="{{ $classification === 'unknown' ? route('cases.edit', [$case, 'classification' => 'unknown']) : route('cases.show', $case) }}">
                                            <span class="case-folder__icon">@include('partials.icon', ['name' => 'folder'])</span>
                                            <span class="case-folder__docket">{{ $case->docket_no }}</span>
                                            <span class="case-folder__title">{{ $case->case_title }}</span>
                                            <span>@include('cases.partials.status-badge', ['status' => $case->status])</span>
                                            <span class="small text-body">{{ __('Year grouping') }}: {{ $case->docket_phase ?? '—' }}</span>
                                            <span class="small text-body">{{ __('Docketed') }}: {{ $case->timeline?->date_of_docket?->format('d M Y') ?? '—' }}</span>
                                            <span class="small text-body">{{ __('Complexity') }}: {{ $case->complexity_weight }}/5</span>
                                            @if (Auth::user()->isSupervisor())
                                                <span class="small text-body">{{ __('Investigator') }}: {{ $case->investigator?->full_name ?? '—' }}</span>
                                            @endif
                                            @if ($classification === 'unknown')<span class="small mt-2">{{ __('Review classification') }}</span>@endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                        <div class="table-responsive" tabindex="0" role="region" aria-label="{{ __('Case records') }}">
                            <table class="table table-striped align-middle table-data">
                                <caption class="visually-hidden">{{ __('Cases, with docket number, title, status, complexity and date of docket.') }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Docket No.') }}</th>
                                        <th scope="col" class="col-title">{{ __('Title') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Year grouping') }}</th>
                                        <th scope="col">{{ __('Complexity') }}</th>
                                        <th scope="col">{{ __('Date of Docket') }}</th>
                                        @if (Auth::user()->isSupervisor())
                                            <th scope="col">{{ __('Investigator') }}</th>
                                        @endif
                                        <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cases as $case)
                                        <tr>
                                            <td class="text-nowrap">{{ $case->docket_no }}</td>
                                            <td class="col-title">{{ $case->case_title }}</td>
                                            <td>@include('cases.partials.status-badge', ['status' => $case->status])</td>
                                            <td>{{ $case->docket_phase ?? '—' }}</td>
                                            <td>@include('cases.partials.weight-meter', ['weight' => $case->complexity_weight])</td>
                                            <td class="text-nowrap">{{ $case->timeline?->date_of_docket?->format('d M Y') ?? '—' }}</td>
                                            @if (Auth::user()->isSupervisor())
                                                <td>{{ $case->investigator?->full_name }}</td>
                                            @endif
                                            <td class="text-end">
                                                <a href="{{ $classification === 'unknown' ? route('cases.edit', [$case, 'classification' => 'unknown']) : route('cases.show', $case) }}" class="btn btn-sm btn-outline-primary">
                                                    {{ $classification === 'unknown' ? __('Review classification') : __('View') }}<span class="visually-hidden"> {{ __('case') }} {{ $case->docket_no }}</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif

                        {{ $cases->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
