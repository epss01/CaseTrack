@php
    $reopening = $case->status === \App\Models\CaseModel::STATUS_CLOSED;
    $closureLabel = $reopening ? __('Reopen case') : __('Close case');
@endphp
<div class="modal fade" id="caseClosure" tabindex="-1" aria-labelledby="closure-title" aria-describedby="closure-description" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('cases.closure.update', $case) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="lock_version" value="{{ $case->lock_version }}">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="closure-title">{{ $closureLabel }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cancel') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold text-break">{{ $case->docket_no }} · {{ $case->case_title }}</p>
                    <p id="closure-description">
                        {{ $reopening
                            ? __('Choose the active status for this case. Reopening returns it to your active caseload.')
                            : __('Change this case to Closed? It will leave your active caseload. You can reopen it later.') }}
                    </p>
                    @if ($reopening)
                        <label for="reopen-status" class="form-label">{{ __('Status after reopening') }}</label>
                        <select id="reopen-status" name="status" class="form-select" required>
                            <option value="">{{ __('Choose a status') }}</option>
                            @foreach (\App\Models\CaseModel::STATUS_COLOURS as $status => $colour)
                                @if ($status !== \App\Models\CaseModel::STATUS_CLOSED)
                                    <option value="{{ $status }}">{{ $status }}</option>
                                @endif
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="status" value="{{ \App\Models\CaseModel::STATUS_CLOSED }}">
                    @endif
                    <p class="text-muted small mt-3 mb-0">{{ __('FIR submission is recorded separately in Timeline. This action does not change those dates.') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ $closureLabel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
