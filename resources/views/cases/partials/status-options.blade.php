{{--
    The ratified five-value status vocabulary (CHR-Answers-2026-08-01, item 2),
    narrowed to what CaseModel::statusRulesFor($case) actually accepts for this
    case: the two closure states stay out of the ordinary edit form (closure runs
    through the maker-checker workflow), except that a case already sitting in one
    of them may resubmit its own current status.

    $case     — the case being edited.
    $selected — the value to preselect (old() on validation failure, else current).
--}}
@php
    $blocked = array_diff(
        [\App\Models\CaseModel::STATUS_PENDING_CLOSURE, \App\Models\CaseModel::STATUS_CLOSED],
        [$case->status]
    );
    $options = array_diff_key(\App\Models\CaseModel::STATUS_COLOURS, array_flip($blocked));
@endphp
@foreach ($options as $status => $colour)
    <option value="{{ $status }}" @selected($selected === $status)>{{ $status }}</option>
@endforeach
