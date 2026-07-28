{{--
    A case status, as a soft badge.

    $status — the raw cases.status value.

    Shared by the dashboard and the case list so the same field cannot render as a
    badge on one page and plain text on the other, one click apart.

    Only two statuses get a colour of their own, and both are constants that
    already drive behaviour: Pending Closure is waiting on a supervisor, Closed is
    out of the active caseload. Everything else — including the free-text
    'Under investigation' and 'For review' — reads as an ordinary open case. This
    view does not decide what any other status means.
--}}
@php
    $statusBadgeClass = match ($status) {
        \App\Models\CaseModel::STATUS_PENDING_CLOSURE => 'badge-soft-amber',
        \App\Models\CaseModel::STATUS_CLOSED => 'badge-soft-slate',
        default => 'badge-soft-blue',
    };
@endphp
<span class="badge {{ $statusBadgeClass }}">{{ $status }}</span>
