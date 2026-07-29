@extends('layouts.app')

@section('title', __('Case Report') . ' · ' . $case->docket_no)

@section('content')
{{-- Narrower than the listing that reaches it, deliberately. This page is a
     definition list, and stretching it to 1576px only pushes each label away
     from its own value. Width helps a table; it does not help a form. --}}
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-11 col-xl-10">

            <div class="identity-bar mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h1 class="identity-bar__title">
                            <span class="identity-bar__eyebrow">{{ __('Case Report') }}</span>
                            <span class="identity-bar__name">{{ $case->docket_no }}</span>
                        </h1>

                        <div class="identity-bar__meta">
                            {{ $case->case_title }}
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        {{-- The report is a read of the case, so editing stays
                             where editing lives. --}}
                        <a href="{{ route('cases.show', $case) }}" class="btn btn-sm btn-outline-light">
                            {{ __('Case profile') }}
                        </a>
                        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-light">
                            {{ __('All reports') }}
                        </a>
                    </div>
                </div>

                <div class="identity-bar__divider"></div>

                <div class="row row-cols-2 row-cols-lg-4 g-3">
                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'layers'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Complexity') }}</span>
                                <span class="stat-tile__value">{{ $case->complexity_weight }}</span>
                                <span class="stat-tile__note">{{ __('of 5') }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'folder'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Complainants') }}</span>
                                <span class="stat-tile__value">{{ $case->complainants->count() }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'folder'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Victims') }}</span>
                                <span class="stat-tile__value">{{ $case->victims->count() }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'folder'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Respondents') }}</span>
                                <span class="stat-tile__value">{{ $case->respondents->count() }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="h6 mb-0">{{ __('Reported fields') }}</h2>
                </div>

                <div class="card-body">
                    {{-- The same fields, in the same order, that this case takes
                         in the listing and the CSV — so a case read on its own
                         cannot say something different from a case read in a
                         batch. --}}
                    <dl class="row mb-0">
                        @foreach ($columns as $name => $label)
                            <dt class="col-sm-4">{{ $label }}</dt>
                            <dd class="col-sm-8">
                                @if ($name === 'status')
                                    @include('cases.partials.status-badge', ['status' => $fields[$name]])
                                @else
                                    {{ $fields[$name] !== null && $fields[$name] !== '' ? $fields[$name] : '—' }}
                                @endif
                            </dd>
                        @endforeach
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="h6 mb-0">{{ __('Statutory deadlines') }}</h2>
                </div>

                <div class="card-body">
                    {{-- What the listing has no room for. A case with no timeline
                         row cannot be measured at all, which is not the same as
                         being on track and must not read as it. --}}
                    @if ($milestones === [])
                        <p class="text-muted mb-0">
                            {{ __('No timeline has been recorded for this case, so no deadline can be measured.') }}
                        </p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <caption class="visually-hidden">
                                    {{ __('Each statutory deadline for this case, with how long is left and whether it has been discharged.') }}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Milestone') }}</th>
                                        <th scope="col">{{ __('Deadline') }}</th>
                                        <th scope="col">{{ __('Due') }}</th>
                                        <th scope="col">{{ __('Alert') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($milestones as $milestone)
                                        @php $remaining = $milestone['days_remaining']; @endphp
                                        <tr>
                                            <td class="text-nowrap">{{ __($milestone['label']) }}</td>
                                            <td class="text-nowrap">{{ $milestone['deadline']->format('d M Y') }}</td>
                                            {{-- days_remaining is already computed by
                                                 CaseDeadlineService; this only words it,
                                                 the same way /alerts does. --}}
                                            <td class="text-nowrap">
                                                @if ($remaining < 0)
                                                    {{ trans_choice('{1} :n day late|[2,*] :n days late', abs($remaining), ['n' => abs($remaining)]) }}
                                                @elseif ($remaining === 0)
                                                    {{ __('Today') }}
                                                @else
                                                    {{ trans_choice('{1} in :n day|[2,*] in :n days', $remaining, ['n' => $remaining]) }}
                                                @endif
                                            </td>
                                            <td>@include('partials.deadline-badge', ['status' => $milestone['status']])</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="h6 mb-0">{{ __('Parties') }}</h2>
                </div>

                <div class="card-body">
                    @foreach ([
                        __('Complainants') => $case->complainants,
                        __('Victims') => $case->victims,
                        __('Respondents') => $case->respondents,
                    ] as $heading => $parties)
                        <h3 class="h6 text-muted">{{ $heading }} <span class="badge badge-soft-slate">{{ $parties->count() }}</span></h3>

                        @if ($parties->isEmpty())
                            <p class="text-muted small">{{ __('None recorded.') }}</p>
                        @else
                            <ul class="list-unstyled small">
                                @foreach ($parties as $party)
                                    <li>
                                        {{ $party->name }}
                                        {{-- Complainants carry a name and nothing else, so the
                                             columns are asked for rather than assumed. A null
                                             status on a respondent is a blank field; on a
                                             complainant there is no field at all. --}}
                                        @if (array_key_exists('status', $party->getAttributes()))
                                            <span class="text-muted">
                                                &mdash;
                                                {{ $party->status ?: __('status not recorded') }},
                                                {{ $party->sector ?: __('sector not recorded') }},
                                                {{ $party->age ?: __('age not recorded') }}
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
