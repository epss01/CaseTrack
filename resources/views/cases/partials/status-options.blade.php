{{--
    The ratified four-value status vocabulary, narrowed by closure authority.
    Assigned investigators may close or reopen their own cases. Other editors
    may not cross the Closed boundary, but may preserve an unchanged Closed value.

    $case     — the case being edited.
    $selected — the value to preselect (old() on validation failure, else current).
--}}
@php
    $options = \App\Models\CaseModel::STATUS_COLOURS;

    if (! Auth::user()->can('changeClosure', $case)) {
        $options = $case->status === \App\Models\CaseModel::STATUS_CLOSED
            ? [\App\Models\CaseModel::STATUS_CLOSED => $options[\App\Models\CaseModel::STATUS_CLOSED]]
            : array_diff_key($options, [\App\Models\CaseModel::STATUS_CLOSED => true]);
    }
@endphp
@foreach ($options as $status => $colour)
    <option value="{{ $status }}" @selected($selected === $status)>{{ $status }}</option>
@endforeach
