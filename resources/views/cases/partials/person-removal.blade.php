@if (($editing ?? false) && filled($row['id'] ?? null))
    <input type="hidden" name="{{ $group }}[{{ $index }}][id]" value="{{ $row['id'] }}">
    <div class="form-check text-start d-flex align-items-center gap-2 ps-0 mt-3">
        <input class="form-check-input ms-0 mt-0 flex-shrink-0" type="checkbox" id="{{ $group }}_{{ $index }}_remove"
               name="{{ $group }}[{{ $index }}][_remove]" value="1" @checked($row['_remove'] ?? false)>
        <label class="form-check-label py-3" for="{{ $group }}_{{ $index }}_remove">{{ __('Remove this person when saving (uncheck to undo)') }}</label>
    </div>
    @error($prefix.'.id')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
    @error($prefix.'._remove')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
@else
    <button type="button" class="btn btn-sm btn-outline-danger remove-row">{{ __('Remove') }}</button>
@endif
