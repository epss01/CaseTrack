{{--
    Ordinary editing never crosses the Closed boundary. Dedicated case-detail
    actions handle closing and reopening with an explicit confirmation.

    $case     — the case being edited.
    $selected — the value to preselect (old() on validation failure, else current).
--}}
@php
    $options = \App\Models\CaseModel::STATUS_COLOURS;

    $options = $case->status === \App\Models\CaseModel::STATUS_CLOSED
        ? [\App\Models\CaseModel::STATUS_CLOSED => $options[\App\Models\CaseModel::STATUS_CLOSED]]
        : array_diff_key($options, [\App\Models\CaseModel::STATUS_CLOSED => true]);
@endphp
@foreach ($options as $status => $colour)
    <option value="{{ $status }}" @selected($selected === $status)>{{ $status }}</option>
@endforeach
