@extends('layouts.app')

@section('title', __('Pending Registrations'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    {{ __('Pending Registrations') }}
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($pending->isEmpty())
                        <p class="mb-0">{{ __('No registrations awaiting approval.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle table-data">
                                <caption class="visually-hidden">{{ __('Pending registrations, with requested role and submission date.') }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col" class="col-title">{{ __('Name') }}</th>
                                        <th scope="col">{{ __('Username') }}</th>
                                        <th scope="col">{{ __('Requested Role') }}</th>
                                        <th scope="col">{{ __('Submitted') }}</th>
                                        <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pending as $user)
                                        <tr>
                                            <td class="col-title">{{ $user->full_name }}</td>
                                            <td>{{ $user->username }}</td>
                                            <td>{{ $user->role->role_name }}</td>
                                            <td class="text-nowrap">{{ $user->created_at->format('d M Y') }}</td>
                                            <td class="text-end">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <form method="POST" action="{{ route('admin.registrations.approve', $user) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                                            {{ __('Approve') }}<span class="visually-hidden"> {{ $user->full_name }}</span>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.registrations.reject', $user) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            {{ __('Reject') }}<span class="visually-hidden"> {{ $user->full_name }}</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
