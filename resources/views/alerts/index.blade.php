@extends('layouts.app')

@section('title', __('Deadline Alerts'))

@php
    use App\Models\CaseModel;
    use App\Services\CaseDeadlineService;

    $user = Auth::user();
    $officeWide = $user->isSupervisor();
@endphp

@section('content')
{{-- Wider than the app's default shell, matching /reports: this page is a
     table first, and .container's 1320px cap left a third of a wide screen
     empty. --}}
<div class="container-fluid container-wide">
    <div class="row justify-content-center">
        <div class="col-12">

            {{-- What the page covers, and the caseload in four figures. Same
                 identity bar and stat tiles as the two dashboards: this is a
                 third view of the same caseload, not a different product. --}}
            <div class="identity-bar mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h1 class="identity-bar__title">
                            <span class="identity-bar__eyebrow">{{ __('Deadline Alerts') }}</span>
                            <span class="identity-bar__name">
                                {{ $officeWide ? __('Office-wide') : __('My cases') }}
                                <span class="role-pill">{{ $user->role?->role_name }}</span>
                            </span>
                        </h1>

                        <div class="identity-bar__meta">
                            {{ $user->office_region }} &middot; {{ now()->translatedFormat('l, d F Y') }}
                        </div>
                    </div>

                    <a href="{{ route('cases.index') }}" class="btn btn-sm btn-outline-light">
                        {{ $officeWide ? __('All cases') : __('My cases') }}
                    </a>
                </div>

                <div class="identity-bar__divider"></div>

                <div class="row row-cols-2 row-cols-lg-4 g-3">
                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'alert-triangle'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Overdue') }}</span>
                                <span class="stat-tile__value">{{ $overdueCount }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'clock'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Due soon') }}</span>
                                <span class="stat-tile__value">{{ $dueSoonCount }}</span>
                                <span class="stat-tile__note">{{ __('within :n days', ['n' => CaseDeadlineService::WARNING_WINDOW_DAYS]) }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'check-circle'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('On track') }}</span>
                                <span class="stat-tile__value">{{ $onTrackCount }}</span>
                                <span class="stat-tile__note">{{ __('nothing outstanding') }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="stat-tile">
                            <span class="stat-tile__icon">@include('partials.icon', ['name' => 'folder'])</span>
                            <span>
                                <span class="stat-tile__label">{{ __('Cases tracked') }}</span>
                                <span class="stat-tile__value">{{ $trackedCount }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">{{ __('Needs attention') }}</h2>
                    <span class="badge badge-soft-blue">{{ $rows->total() }}</span>
                </div>

                <div class="card-body">
                    @if ($rows->isEmpty())
                        <p class="mb-0">{{ __('No deadline needs attention.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle table-data">
                                <caption class="visually-hidden">
                                    {{ __('Cases with a statutory deadline that has passed or falls within the next :n days, soonest first.', ['n' => CaseDeadlineService::WARNING_WINDOW_DAYS]) }}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Docket No.') }}</th>
                                        <th scope="col" class="col-title">{{ __('Title') }}</th>
                                        @if ($officeWide)
                                            <th scope="col">{{ __('Investigator') }}</th>
                                        @endif
                                        <th scope="col">{{ __('Milestone') }}</th>
                                        <th scope="col">{{ __('Deadline') }}</th>
                                        <th scope="col">{{ __('Due') }}</th>
                                        <th scope="col">{{ __('Alert') }}</th>
                                        <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        @php
                                            $case = $row['case'];
                                            $milestone = $row['milestone'];
                                            $remaining = $milestone['days_remaining'];
                                        @endphp
                                        <tr @class([
                                            'row-pending table-warning' => $milestone['status'] === CaseDeadlineService::STATUS_OVERDUE,
                                        ])>
                                            {{-- nowrap only where breaking the value costs more
                                                 than the width does: a docket number is an
                                                 identifier and reads badly split. A name is not,
                                                 and holding it on one line cost ~90px that the
                                                 title column was paying for. --}}
                                            <td class="text-nowrap">
                                                @if ($milestone['status'] === CaseDeadlineService::STATUS_OVERDUE)
                                                    <span class="row-flag row-flag--red">@include('partials.icon', ['name' => 'alert-triangle'])</span>
                                                @endif
                                                {{ $case->docket_no }}
                                            </td>
                                            <td class="col-title">{{ $case->case_title }}</td>
                                            @if ($officeWide)
                                                <td>{{ $case->investigator?->full_name ?? '—' }}</td>
                                            @endif
                                            <td class="text-nowrap">{{ __($milestone['label']) }}</td>
                                            <td class="text-nowrap">{{ $milestone['deadline']->format('d M Y') }}</td>
                                            {{-- days_remaining is already computed by
                                                 CaseDeadlineService; this only words it. --}}
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
                                            <td class="text-end">
                                                <a href="{{ route('cases.show', $case) }}" class="btn btn-sm btn-outline-primary">
                                                    {{ __('View') }}<span class="visually-hidden"> {{ __('case') }} {{ $case->docket_no }}</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $rows->links() }}
                    @endif

                    {{-- A case with no timeline row cannot be measured at all. Saying so
                         is the point: leaving it out of every figure would let the page
                         report that a case is fine when it was never checked. --}}
                    @if ($untrackedCount > 0)
                        <p class="text-muted small mb-0 mt-3">
                            {{ trans_choice(
                                '{1} :n active case has no timeline recorded and cannot be tracked.|[2,*] :n active cases have no timeline recorded and cannot be tracked.',
                                $untrackedCount,
                                ['n' => $untrackedCount]
                            ) }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
