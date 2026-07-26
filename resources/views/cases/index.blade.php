@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        @if (Auth::user()->isSupervisor())
                            {{ __('All Cases') }} &mdash; {{ Auth::user()->office_region }}
                        @else
                            {{ __('My Cases') }}
                        @endif
                    </span>

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
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('Docket No.') }}</th>
                                        <th>{{ __('Title') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        @if (Auth::user()->isSupervisor())
                                            <th>{{ __('Investigator') }}</th>
                                        @endif
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cases as $case)
                                        <tr>
                                            <td>{{ $case->docket_no }}</td>
                                            <td>{{ $case->case_title }}</td>
                                            <td>{{ $case->status }}</td>
                                            @if (Auth::user()->isSupervisor())
                                                <td>{{ $case->investigator?->full_name }}</td>
                                            @endif
                                            <td class="text-end">
                                                <a href="{{ route('cases.show', $case) }}" class="btn btn-sm btn-outline-primary">
                                                    {{ __('View') }}
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
