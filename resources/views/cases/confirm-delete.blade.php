@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">{{ __('Delete Case') }}</div>

                <div class="card-body">
                    <p>{{ __('Delete this case? It disappears from the case list along with everything filed under it.') }}</p>

                    <dl class="row">
                        <dt class="col-sm-3">{{ __('Docket No.') }}</dt>
                        <dd class="col-sm-9">{{ $case->docket_no }}</dd>

                        <dt class="col-sm-3">{{ __('Case Title') }}</dt>
                        <dd class="col-sm-9">{{ $case->case_title }}</dd>

                        <dt class="col-sm-3">{{ __('Investigator') }}</dt>
                        <dd class="col-sm-9 mb-0">{{ $case->investigator?->full_name ?? '—' }}</dd>
                    </dl>

                    <p class="text-muted small">
                        {{ __('The record is retained for the audit trail and the deletion is logged against your account.') }}
                    </p>

                    <form method="POST" action="{{ route('cases.destroy', $case) }}">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="btn btn-danger">{{ __('Delete Case') }}</button>

                        <a href="{{ route('cases.show', $case) }}" class="btn btn-link">{{ __('Cancel') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
