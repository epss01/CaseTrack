@extends('layouts.app')

@section('title', Auth::user()->isSupervisor() ? __('All Cases') : __('My Cases'))

@section('content')
{{-- Wider than the app's default shell, matching /alerts and /reports: this
     page is a table first, and .container's 1320px cap left a third of a
     wide screen empty. --}}
<div class="container-fluid container-wide">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="h6 mb-0">{{ __('Search') }}</h2>
                </div>

                <div class="card-body">
                    <form method="GET" action="{{ route('cases.index') }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-sm-8 col-lg-6">
                                <label for="search" class="form-label">{{ __('Docket no., title, or party name') }}</label>
                                <input type="text" class="form-control @error('search') is-invalid @enderror"
                                       id="search" name="search" value="{{ $search ?? '' }}"
                                       autocomplete="off">
                                @error('search')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-12 d-flex gap-2 justify-content-end">
                                @if (filled($search))
                                    <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary">
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h1 class="h6 mb-0">
                        @if (Auth::user()->isSupervisor())
                            {{ __('All Cases') }} &mdash; {{ Auth::user()->office_region }}
                        @else
                            {{ __('My Cases') }}
                        @endif
                    </h1>

                    @can('create', App\Models\CaseModel::class)
                        <a href="{{ route('cases.create') }}" class="btn btn-sm btn-primary">{{ __('New Case') }}</a>
                    @endcan
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($cases->isEmpty())
                        <p class="mb-0">{{ filled($search) ? __('No cases match this search.') : __('No cases to show.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle table-data">
                                <caption class="visually-hidden">{{ __('Cases, with docket number, title, status, complexity and date of docket.') }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Docket No.') }}</th>
                                        <th scope="col">{{ __('Title') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
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
                                            <td>{{ $case->case_title }}</td>
                                            <td>@include('cases.partials.status-badge', ['status' => $case->status])</td>
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
