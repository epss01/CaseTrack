@extends('layouts.app')

@section('title', __('Audit Log'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-11">
            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="h6 mb-0">{{ __('Filter') }}</h2>
                </div>

                <div class="card-body">
                    <form method="GET" action="{{ route('admin.audit-logs.index') }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-sm-6 col-lg-3">
                                <label for="user_id" class="form-label">{{ __('Acting user') }}</label>
                                <select class="form-select @error('user_id') is-invalid @enderror"
                                        id="user_id" name="user_id">
                                    <option value="">{{ __('Any user') }}</option>
                                    @foreach ($users as $candidate)
                                        <option value="{{ $candidate->id }}" @selected(($filters['user_id'] ?? null) == $candidate->id)>
                                            {{ $candidate->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <label for="action" class="form-label">{{ __('Action') }}</label>
                                <select class="form-select @error('action') is-invalid @enderror"
                                        id="action" name="action">
                                    <option value="">{{ __('Any action') }}</option>
                                    @foreach ($actions as $action)
                                        <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>
                                            {{ $action }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('action')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <label for="from" class="form-label">{{ __('From') }}</label>
                                <input type="date" class="form-control @error('from') is-invalid @enderror"
                                       id="from" name="from" value="{{ $filters['from'] ?? '' }}">
                                @error('from')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-sm-6 col-lg-3">
                                <label for="to" class="form-label">{{ __('To') }}</label>
                                <input type="date" class="form-control @error('to') is-invalid @enderror"
                                       id="to" name="to" value="{{ $filters['to'] ?? '' }}">
                                @error('to')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('Apply') }}</button>
                                <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Clear') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    {{ __('Audit Log') }}
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-data">
                            <caption class="visually-hidden">{{ __('Every recorded action, who performed it, who or what it was performed on, and when.') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Timestamp') }}</th>
                                    <th scope="col">{{ __('Actor') }}</th>
                                    <th scope="col">{{ __('Action') }}</th>
                                    <th scope="col">{{ __('Target') }}</th>
                                    <th scope="col">{{ __('Case') }}</th>
                                    <th scope="col">{{ __('Notes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($logs as $log)
                                    <tr>
                                        <td class="text-nowrap">{{ $log->timestamp->format('Y-m-d H:i:s') }}</td>
                                        <td>{{ $log->user?->full_name ?? __('(deleted user)') }}</td>
                                        <td><code>{{ $log->action_performed }}</code></td>
                                        <td>{{ $log->targetUser?->full_name ?? '—' }}</td>
                                        {{-- Plain text, not a link: Admin never opens, views, or
                                             edits a case (CLAUDE.md, Roles/access rules), and
                                             reports.show would 403 the only role that can see
                                             this page. --}}
                                        <td>{{ $log->case?->docket_no ?? '—' }}</td>
                                        <td>{{ $log->notes ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">{{ __('No matching entries.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
