@if ($case->is_torture_case === null)
    <p class="text-muted">{{ __('60-day deadline not evaluated: case classification is unknown.') }}</p>
@elseif ($case->is_torture_case && $tortureMilestone)
    <p>
        <strong>{{ __('Effective RORP deadline') }}:</strong> {{ $tortureMilestone['deadline']->format('d M Y') }}.
        {{ $case->timeline?->extension_60th_days ? __('External 30-day extension recorded.') : __('No external extension recorded.') }}
    </p>
@endif
