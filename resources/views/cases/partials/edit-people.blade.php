<fieldset class="mt-4">
    <legend class="h6">{{ __('People') }}</legend>
    <p class="text-muted">{{ __('Correct existing records or add people. Removal takes effect only when you save. Uncheck a removal to undo it. Each group may be empty.') }}</p>
    @foreach (['complainants' => __('Complainants'), 'victims' => __('Victims'), 'respondents' => __('Respondents')] as $group => $label)
        @php
            $stored = $case->{$group}->sortBy('id')->map(fn ($person) => $person->only($group === 'complainants' ? ['id', 'name'] : ['id', 'name', 'age', 'status', 'sector']))->values()->all();
            $rows = old($group, $stored);
            $rows = is_array($rows) ? $rows : $stored;
            $blank = $group === 'complainants' ? ['name' => ''] : ['name' => '', 'age' => '', 'status' => '', 'sector' => ''];
            $addLabel = ['complainants' => __('Add complainant'), 'victims' => __('Add victim'), 'respondents' => __('Add respondent')][$group];
        @endphp
        <section class="mb-4" aria-labelledby="{{ $group }}-heading">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 id="{{ $group }}-heading" class="h6 mb-0">{{ $label }}</h2>
                <button type="button" class="btn btn-outline-primary add-row" data-group="{{ $group }}">{{ $addLabel }}</button>
            </div>
            @error($group)<p class="text-danger" role="alert">{{ $message }}</p>@enderror
            <p data-empty-group="{{ $group }}" @if (count($rows)) hidden @endif>{{ __('No :group recorded.', ['group' => strtolower($label)]) }}</p>
            <div data-rows="{{ $group }}" data-next-index="{{ count($rows) ? max(array_map('intval', array_keys($rows))) + 1 : 0 }}">
                @foreach ($rows as $index => $row)
                    @if (is_array($row))
                        @if ($group === 'complainants')
                            @include('cases.partials.complainant-row', ['row' => $row, 'editing' => true])
                        @else
                            @include('cases.partials.party-row', ['row' => $row, 'editing' => true, 'optional' => $group === 'respondents'])
                        @endif
                    @endif
                @endforeach
            </div>
            <template id="{{ $group }}-template">
                @if ($group === 'complainants')
                    @include('cases.partials.complainant-row', ['index' => '__INDEX__', 'row' => $blank, 'editing' => true])
                @else
                    @include('cases.partials.party-row', ['index' => '__INDEX__', 'row' => $blank, 'editing' => true, 'optional' => $group === 'respondents'])
                @endif
            </template>
        </section>
    @endforeach
    <p class="visually-hidden" role="status" data-people-feedback></p>
    <noscript><p>{{ __('Adding rows needs JavaScript. Existing records and removal checkboxes can still be saved without it.') }}</p></noscript>
</fieldset>
