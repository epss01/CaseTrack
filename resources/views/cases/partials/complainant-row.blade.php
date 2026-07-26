{{--
    One repeater row for a complainant. Complainants carry a name only.

    $index - array index, or the literal __INDEX__ inside the JS template
    $row   - previously submitted values, if any

    Keeps the .party-row class and .remove-row button so the repeater script
    in cases/create.blade.php drives this the same way it drives the
    victim/respondent rows.
--}}
@php
    $prefix = "complainants.{$index}";
    $isTemplate = $index === '__INDEX__';
@endphp

<div class="party-row border rounded p-3 mb-3">
    <div class="row g-3">
        <div class="col-md-9">
            <label class="form-label" for="complainants_{{ $index }}_name">{{ __('Name') }}</label>
            <input id="complainants_{{ $index }}_name"
                   type="text"
                   class="form-control @if (! $isTemplate) @error($prefix.'.name') is-invalid @enderror @endif"
                   name="complainants[{{ $index }}][name]"
                   value="{{ $row['name'] ?? '' }}">
            @if (! $isTemplate)
                @error($prefix.'.name')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            @endif
        </div>

        <div class="col-md-3 d-flex align-items-end justify-content-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-row">{{ __('Remove') }}</button>
        </div>
    </div>
</div>
