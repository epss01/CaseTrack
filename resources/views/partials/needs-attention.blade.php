<section class="card mb-3" aria-labelledby="attention-heading">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0" id="attention-heading">{{ __('Needs attention') }}</h2>
        <a href="{{ route('alerts.index') }}">{{ __('View all alerts') }}</a>
    </div>
    <div class="card-body">
        <p class="text-muted">{{ __('Up to five cases with overdue obligations or obligations due within 14 days, earliest deadline first.') }}</p>
        @if ($attentionRows)
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <caption class="visually-hidden">{{ __('Cases needing attention and their next outstanding obligation.') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Case') }}</th>
                            @if ($officeWide)<th scope="col">{{ __('Investigator') }}</th>@endif
                            <th scope="col">{{ __('Obligation') }}</th>
                            <th scope="col">{{ __('Due date') }}</th>
                            <th scope="col">{{ __('Deadline status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attentionRows as $row)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('cases.show', $row['case']) }}#case-timeline">{{ $row['case']->docket_no }}</a>
                                    <span class="d-block fw-normal">{{ $row['case']->case_title }}</span>
                                </th>
                                @if ($officeWide)<td>{{ $row['case']->investigator?->full_name ?? '—' }}</td>@endif
                                <td>{{ $row['milestone']['label'] }}</td>
                                <td class="text-nowrap">{{ $row['milestone']['deadline']->format('d M Y') }}</td>
                                <td>@include('partials.deadline-badge', ['status' => $row['milestone']['status']])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mb-0">{{ __('No tracked obligations are overdue or due within 14 days.') }}</p>
        @endif
        @if ($untrackedCount > 0)
            <p class="text-muted mb-0 mt-3">{{ trans_choice('{1} :n active case has no docket date or timeline; its deadlines cannot be assessed.|[2,*] :n active cases have no docket date or timeline; their deadlines cannot be assessed.', $untrackedCount, ['n' => $untrackedCount]) }}</p>
        @endif
    </div>
</section>
