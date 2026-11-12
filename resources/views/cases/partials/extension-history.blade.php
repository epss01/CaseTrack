@php
    $extensionActions = [
        \App\Models\AuditLog::ACTION_TORTURE_EXTENSION_RECORDED => __('Extension recorded'),
        \App\Models\AuditLog::ACTION_TORTURE_EXTENSION_RECALCULATED => __('Extension recalculated'),
        \App\Models\AuditLog::ACTION_TORTURE_EXTENSION_REMOVED => __('Extension removed'),
    ];
@endphp
<section class="mt-4" id="extension-history" tabindex="-1" aria-labelledby="extension-history-heading">
    <h3 id="extension-history-heading" class="h6">{{ __('Extension history') }}</h3>
    <p class="small text-muted">{{ __('Newest entries first. These entries record external decisions entered by staff; CaseTrack does not approve extensions.') }}</p>
    <p class="small text-muted">{{ __('Dates below are recorded extension deadlines. None recorded means no extension date; other case deadlines still apply.') }}</p>

    @if ($extensionHistory->total() === 0)
        @if ($timeline?->extension_60th_days)
            <p>{{ __('An extension deadline is stored, but no extension audit history is available. The approval reference, recorder and recorded time are unknown.') }}</p>
        @else
            <p>{{ __('No extension history recorded.') }}</p>
        @endif
    @else
        <ul class="list-unstyled mb-3" aria-label="{{ __('Extension events') }}">
            @forelse ($extensionHistory as $event)
                @php $details = $event->extensionHistoryDetails(); @endphp
                <li class="border-top py-3">
                    <h4 class="h6">{{ $extensionActions[$event->action_performed] }}</h4>
                    <dl class="row small mb-0">
                        <dt class="col-sm-4">{{ __('Recorded') }}</dt>
                        <dd class="col-sm-8">
                            @if ($event->timestamp)
                                <time datetime="{{ $event->timestamp->toAtomString() }}">{{ $event->timestamp->format('d M Y H:i T') }}</time>
                            @else
                                {{ __('Unknown time') }}
                            @endif
                        </dd>
                        <dt class="col-sm-4">{{ __('Recorded by') }}</dt>
                        <dd class="col-sm-8 text-break">{{ filled($event->user?->full_name) ? $event->user->full_name : __('Account unavailable') }}</dd>
                        @if ($details !== null)
                            <dt class="col-sm-4">{{ __('Previous extension deadline') }}</dt>
                            <dd class="col-sm-8">{{ $details['previous_deadline'] ?? __('None recorded') }}</dd>
                            <dt class="col-sm-4">{{ __('Resulting extension deadline') }}</dt>
                            <dd class="col-sm-8">{{ $details['deadline'] ?? __('None recorded') }}</dd>
                            <dt class="col-sm-4">{{ __('Approval reference / note') }}</dt>
                            <dd class="col-sm-8 text-break mb-0">{{ filled($details['note']) ? $details['note'] : __('Not recorded') }}</dd>
                        @else
                            <dt class="col-sm-4">{{ __('Historical details') }}</dt>
                            <dd class="col-sm-8 mb-0">{{ __('Unavailable: the historical note does not contain a readable extension record.') }}</dd>
                        @endif
                    </dl>
                </li>
            @empty
                <li>{{ __('No extension entries on this page.') }} <a href="{{ route('cases.show', $case) }}#extension-history">{{ __('Return to the first history page') }}</a></li>
            @endforelse
        </ul>
        {{ $extensionHistory->links() }}
    @endif
</section>
