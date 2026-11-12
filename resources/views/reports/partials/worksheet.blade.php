<div class="table-responsive" tabindex="0" role="region" aria-label="{{ __('CHR monitoring records; scroll horizontally for all columns') }}">
    <table class="table table-bordered table-data chr-worksheet">
        <caption>{{ __('CHR monitoring fields, with CaseTrack status and year grouping for context. Scroll horizontally for people and timeline dates. Dashes mean no value is recorded.') }}</caption>
        <thead>
            <tr>
                <th rowspan="2" scope="col">{{ __('No.') }}</th>
                <th rowspan="2" scope="col">{{ __('Docket No.') }}</th>
                <th rowspan="2" scope="col">{{ __('Case title') }}</th>
                <th rowspan="2" scope="col">{{ __('Complainant') }}</th>
                <th colspan="4" scope="colgroup">{{ __('Victim/s') }}</th>
                <th colspan="4" scope="colgroup">{{ __('Respondent/s') }}</th>
                <th rowspan="2" scope="col">{{ __('Date/place of incident') }}</th>
                <th rowspan="2" scope="col">{{ __('Source of information') }}</th>
                <th colspan="5" scope="colgroup">{{ __('Timeline tracking') }}</th>
                <th colspan="2" scope="colgroup">{{ __('FIR submission') }}</th>
                <th rowspan="2" scope="col">{{ __('Submitted to') }}</th>
                <th rowspan="2" scope="col">{{ __('Status') }}</th>
                <th rowspan="2" scope="col">{{ __('Year grouping') }}</th>
                @if ($officeWide)<th rowspan="2" scope="col">{{ __('Investigator') }}</th>@endif
            </tr>
            <tr>
                @foreach ([__('Name'), __('Age'), __('Status'), __('Sector'), __('Name'), __('Age'), __('Status'), __('Sector'), __('Date of docket'), __('Initial report (ROP) submitted'), __('30-day extension requested'), __('60th-day RORP submitted (torture cases)'), __('120th-day ROP submitted'), __('Target date of FIR'), __('FIR submitted')] as $heading)
                    <th scope="col">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $entry)
                @php $case = $entry['case']; $caseNumber = $rows->firstItem() + $loop->index; @endphp
                @foreach ($entry['worksheetRows'] as $values)
                    <tr>
                        <td>{{ $caseNumber }}</td>
                        <th scope="row"><a href="{{ route('reports.show', $case) }}">{{ $case->docket_no }}</a></th>
                        @foreach (array_slice($values, 2) as $value)
                            <td>{{ $value === null || $value === '' ? '—' : $value }}</td>
                        @endforeach
                        <td>@include('cases.partials.status-badge', ['status' => $case->status])</td>
                        <td>{{ $case->docket_phase ?? '—' }}</td>
                        @if ($officeWide)<td>{{ $case->investigator?->full_name ?? '—' }}</td>@endif
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>
