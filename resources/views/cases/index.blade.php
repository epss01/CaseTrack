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
            <div class="card filter-panel mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('cases.index') }}">
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

                            <div class="filter-actions">
                                @if (filled($search))
                                    <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary">
                                        {{ __('Clear') }}
                                    </a>
                                @endif
                                <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($cases->isEmpty())
                        <p class="mb-0">{{ filled($search) ? __('No cases match this search.') : __('No cases to show.') }}</p>
                    @else
                        <div class="table-responsive" tabindex="0" role="region" aria-label="{{ __('Case records') }}">
                            <table class="table table-striped align-middle table-data">
                                <caption class="visually-hidden">{{ __('Cases, with docket number, title, status, complexity and date of docket.') }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Docket No.') }}</th>
                                        <th scope="col" class="col-title">{{ __('Title') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Phase') }}</th>
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
                                                <a href="{{ route('cases.show', $case) }}" class="btn btn-sm btn-outline-primary">
                                                    {{ __('View') }}<span class="visually-hidden"> {{ __('case') }} {{ $case->docket_no }}</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $cases->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
