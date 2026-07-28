{{--
    A deadline's state, as a badge.

    $status — one of CaseDeadlineService's STATUS_* values.

    Sibling of cases/partials/status-badge.blade.php and deliberately not the
    same file: that one maps a case's free-text status, this one maps a computed
    alert level. They share the tint classes, not the vocabulary, and merging
    them would tie an unratified status list to a fixed one.
--}}
@php
    use App\Services\CaseDeadlineService;

    [$deadlineBadgeClass, $deadlineBadgeLabel] = match ($status) {
        CaseDeadlineService::STATUS_OVERDUE => ['badge-soft-red', __('Overdue')],
        CaseDeadlineService::STATUS_DUE_SOON => ['badge-soft-amber', __('Due soon')],
        CaseDeadlineService::STATUS_SUBMITTED => ['badge-soft-slate', __('Submitted')],
        default => ['badge-soft-blue', __('On track')],
    };
@endphp

<span class="badge {{ $deadlineBadgeClass }}">{{ $deadlineBadgeLabel }}</span>
