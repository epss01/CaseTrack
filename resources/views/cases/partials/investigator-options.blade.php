{{--
    The Assign To options, shared by intake and reassignment.

    $investigators arrives ordered by Workload Capacity Score, least loaded
    first, so the first option is the one the score suggests.
--}}
<option value="">{{ __('Select an investigator') }}</option>
@foreach ($investigators as $investigator)
    <option value="{{ $investigator->id }}" @selected($selected == $investigator->id)>
        {{ $investigator->full_name }}
        &mdash; {{ __('WCS') }} {{ number_format($investigator->workloadCapacityScore(), 1) }}
        @if ($loop->first) ({{ __('suggested') }}) @endif
    </option>
@endforeach
