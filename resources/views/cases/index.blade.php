@extends('layouts.app')

@section('title', Auth::user()->isSupervisor() ? __('All Cases') : __('My Cases'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
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
                        <p class="mb-0">{{ __('No cases to show.') }}</p>
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
