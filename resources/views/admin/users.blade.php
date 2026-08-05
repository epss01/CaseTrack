@extends('layouts.app')

@section('title', __('Manage Accounts'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-11">
            <div class="card">
                <div class="card-header">
                    {{ __('Manage Accounts') }}
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table align-middle table-data">
                            <caption class="visually-hidden">{{ __('Every approved account, with role, status, and account actions.') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="col-title">{{ __('Name') }}</th>
                                    <th scope="col">{{ __('Username') }}</th>
                                    <th scope="col">{{ __('Role') }}</th>
                                    <th scope="col">{{ __('Status') }}</th>
                                    <th scope="col">{{ __('Reset Password') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    @php
                                        $isSelf = $user->is(Auth::user());
                                        $isAdminRow = $user->isAdmin();
                                    @endphp
                                    <tr>
                                        <td class="col-title">
                                            {{ $user->full_name }}
                                            @if ($isSelf)
                                                <span class="badge bg-secondary">{{ __('you') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $user->username }}</td>
                                        <td>
                                            {{-- Admin rows and the acting admin's own row are never
                                                 role-changeable from here: Admin is provisioned only
                                                 via make:admin, and self-role-change risks lockout. --}}
                                            @if ($isAdminRow || $isSelf)
                                                {{ $user->role->role_name }}
                                            @else
                                                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="d-flex gap-2 flex-nowrap">
                                                    @csrf
                                                    @method('PUT')

                                                    <label for="role_id_{{ $user->id }}" class="visually-hidden">
                                                        {{ __('Role for :name', ['name' => $user->full_name]) }}
                                                    </label>
                                                    <select id="role_id_{{ $user->id }}" name="role_id" class="form-select form-select-sm">
                                                        @foreach ($roles as $role)
                                                            <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->role_name }}</option>
                                                        @endforeach
                                                    </select>

                                                    <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($isSelf)
                                                <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ $user->is_active ? __('Active') : __('Inactive') }}
                                                </span>
                                            @else
                                                <form method="POST" action="{{ route('admin.users.active', $user) }}" class="d-flex align-items-center gap-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="is_active" value="{{ $user->is_active ? '0' : '1' }}">

                                                    <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                        {{ $user->is_active ? __('Active') : __('Inactive') }}
                                                    </span>

                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                        {{ $user->is_active ? __('Deactivate') : __('Activate') }}<span class="visually-hidden"> {{ $user->full_name }}</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                        <td>
                                            @unless ($isSelf)
                                                <form method="POST" action="{{ route('admin.users.password', $user) }}" class="d-flex flex-wrap gap-2 align-items-center">
                                                    @csrf
                                                    @method('PUT')

                                                    <label for="password_{{ $user->id }}" class="visually-hidden">
                                                        {{ __('New password for :name', ['name' => $user->full_name]) }}
                                                    </label>
                                                    <input id="password_{{ $user->id }}" type="password" name="password"
                                                           class="form-control form-control-sm" style="width: 9rem;"
                                                           placeholder="{{ __('New password') }}" minlength="8" required autocomplete="new-password">

                                                    <label for="password_confirmation_{{ $user->id }}" class="visually-hidden">
                                                        {{ __('Confirm new password for :name', ['name' => $user->full_name]) }}
                                                    </label>
                                                    <input id="password_confirmation_{{ $user->id }}" type="password" name="password_confirmation"
                                                           class="form-control form-control-sm" style="width: 9rem;"
                                                           placeholder="{{ __('Confirm') }}" minlength="8" required autocomplete="new-password">

                                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                                        {{ __('Reset') }}<span class="visually-hidden"> {{ __('password for') }} {{ $user->full_name }}</span>
                                                    </button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
