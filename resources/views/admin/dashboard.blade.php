@extends('layouts.app')

@section('title', __('Admin Dashboard'))

@section('content')
<div class="container-fluid container-wide">
    <div class="page-heading">
        <div>
            <h1>{{ __('Admin dashboard') }}</h1>
            <p class="text-muted mb-0">{{ __('Registrations and account access') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary">{{ __('Accounts') }}</a>
            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline-secondary">{{ __('Audit Log') }}</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([__('Pending registrations') => $pendingCount, __('Active accounts') => $activeCount, __('Inactive accounts') => $inactiveCount] as $label => $count)
            <div class="col-md-4">
                <section class="card h-100">
                    <div class="card-body">
                        <h2 class="h6">{{ $label }}</h2>
                        <p class="analytics-value mb-0">{{ number_format($count) }}</p>
                    </div>
                </section>
            </div>
        @endforeach
    </div>

    <section class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2>{{ __('Awaiting review') }}</h2>
                    <p class="text-muted mb-0">{{ __('Up to five oldest pending registrations.') }}</p>
                </div>
                <a href="{{ route('admin.registrations.index') }}" class="btn btn-primary">{{ __('Review registrations') }}</a>
            </div>
            @if ($pending->isEmpty())
                <p class="mb-0">{{ __('No registrations awaiting review.') }}</p>
            @else
                <div class="table-responsive" tabindex="0" role="region" aria-label="{{ __('Pending registrations') }}">
                    <table class="table table-data align-middle mb-0">
                        <caption class="visually-hidden">{{ __('Oldest registrations awaiting review') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Name') }}</th>
                                <th scope="col">{{ __('Requested role') }}</th>
                                <th scope="col">{{ __('Submitted') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $registration)
                                <tr>
                                    <td>{{ $registration->full_name }}</td>
                                    <td>{{ $registration->role->role_name }}</td>
                                    <td class="text-nowrap">{{ $registration->created_at->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
