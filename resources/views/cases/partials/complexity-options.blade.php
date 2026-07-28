{{--
    C_j, the case complexity weight. A 1-5 judgment shared by intake and the
    edit form, with the ends labelled so the scale means the same thing to
    everyone entering it.
--}}
<option value="">{{ __('Select a weight') }}</option>
@foreach ([1 => __('1 — Straightforward'), 2 => '2', 3 => __('3 — Moderate'), 4 => '4', 5 => __('5 — Highly complex')] as $weight => $label)
    <option value="{{ $weight }}" @selected((string) $selected === (string) $weight)>{{ $label }}</option>
@endforeach
