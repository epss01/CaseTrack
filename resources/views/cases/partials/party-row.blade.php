{{--
    One repeater row for a victim or respondent.

    $group    - "victims" or "respondents"
    $index    - array index, or the literal __INDEX__ inside the JS template
    $row      - previously submitted values, if any
    $optional - true when status/sector may be left blank (respondents)
--}}
@php
    $prefix = "{$group}.{$index}";
    $isTemplate = $index === '__INDEX__';
@endphp

<div class="party-row border rounded p-3 mb-3">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="{{ $group }}_{{ $index }}_name">{{ __('Name') }}</label>
            <input id="{{ $group }}_{{ $index }}_name"
                   type="text"
                   class="form-control @if (! $isTemplate) @error($prefix.'.name') is-invalid @enderror @endif"
                   name="{{ $group }}[{{ $index }}][name]"
                   value="{{ $row['name'] ?? '' }}">
            @if (! $isTemplate)
                @error($prefix.'.name')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            @endif
        </div>

        <div class="col-md-2">
            <label class="form-label" for="{{ $group }}_{{ $index }}_age">{{ __('Age') }}</label>
            <input id="{{ $group }}_{{ $index }}_age"
                   type="number"
                   min="0"
                   max="255"
                   class="form-control @if (! $isTemplate) @error($prefix.'.age') is-invalid @enderror @endif"
                   name="{{ $group }}[{{ $index }}][age]"
                   value="{{ $row['age'] ?? '' }}">
            @if (! $isTemplate)
                @error($prefix.'.age')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            @endif
        </div>

        <div class="col-md-3">
            <label class="form-label" for="{{ $group }}_{{ $index }}_status">
                {{ __('Status') }}
                @if ($optional)<span class="text-muted small">({{ __('optional') }})</span>@endif
            </label>
            <input id="{{ $group }}_{{ $index }}_status"
                   type="text"
                   class="form-control @if (! $isTemplate) @error($prefix.'.status') is-invalid @enderror @endif"
                   name="{{ $group }}[{{ $index }}][status]"
                   value="{{ $row['status'] ?? '' }}">
            @if (! $isTemplate)
                @error($prefix.'.status')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            @endif
        </div>

        <div class="col-md-3">
            <label class="form-label" for="{{ $group }}_{{ $index }}_sector">
                {{ __('Sector') }}
                @if ($optional)<span class="text-muted small">({{ __('optional') }})</span>@endif
            </label>
            <input id="{{ $group }}_{{ $index }}_sector"
                   type="text"
                   class="form-control @if (! $isTemplate) @error($prefix.'.sector') is-invalid @enderror @endif"
                   name="{{ $group }}[{{ $index }}][sector]"
                   value="{{ $row['sector'] ?? '' }}">
            @if (! $isTemplate)
                @error($prefix.'.sector')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            @endif
        </div>
    </div>

    <div class="text-end mt-2">
        <button type="button" class="btn btn-sm btn-outline-danger remove-row">{{ __('Remove') }}</button>
    </div>
</div>
