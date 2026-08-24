@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-warning">
                <div class="card-header bg-warning">{{ __('Review Proposed Closure') }}</div>

                <div class="card-body">
                    <p>{{ __('The investigator on this case has asked for it to be closed. Closing it takes the case out of their active caseload.') }}</p>

                    <dl class="row">
                        <dt class="col-sm-4">{{ __('Docket No.') }}</dt>
                        <dd class="col-sm-8">{{ $case->docket_no }}</dd>

                        <dt class="col-sm-4">{{ __('Case Title') }}</dt>
                        <dd class="col-sm-8">{{ $case->case_title }}</dd>

                        <dt class="col-sm-4">{{ __('Investigator') }}</dt>
                        <dd class="col-sm-8">{{ $case->investigator?->full_name ?? '—' }}</dd>

                        {{-- Where the case goes back to if this is rejected. --}}
                        <dt class="col-sm-4">{{ __('Status Before Proposal') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $case->status_before_closure ?? '—' }}</dd>
                    </dl>

                    <p class="text-muted small">
                        {{ __('Either decision is logged against your account. Rejecting returns the case to the status shown above.') }}
                    </p>

                    <form method="POST" action="{{ route('cases.closure.resolve', $case) }}">
                        @csrf
                        @method('PUT')

                        <button type="submit" name="decision" value="confirm" class="btn btn-success">
                            {{ __('Confirm Closure') }}
                        </button>

                        <button type="submit" name="decision" value="reject" class="btn btn-outline-danger">
                            {{ __('Reject Closure') }}
                        </button>

                        <a href="{{ route('cases.show', $case) }}" class="btn btn-link">{{ __('Cancel') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
