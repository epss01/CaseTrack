<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\CaseModel;
use App\Models\Role;
use App\Models\User;
use App\Services\CaseAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Arr;

class AnalyticsController extends Controller
{
    /**
     * CEPEJ Clearance Rate and Disposition Time for the visible caseload.
     *
     * @return Renderable
     */
    public function index(ReportFilterRequest $request, CaseAnalyticsService $analytics)
    {
        $filters = $request->filters();
        $today = CarbonImmutable::today();
        $from = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'])
            : $today->startOfYear();
        $to = isset($filters['to'])
            ? CarbonImmutable::parse($filters['to'])
            : $today;

        $filters['from'] = $from->toDateString();
        $filters['to'] = $to->toDateString();

        $cases = CaseModel::query()
            ->visibleTo($request->user())
            ->filteredBy(Arr::except($filters, ['from', 'to']))
            ->with(['timeline', 'investigator'])
            ->get();

        return view('reports.analytics', [
            'filters' => $filters,
            'metrics' => $analytics->clearanceAndDisposition($cases, $from, $to),
            'periodFrom' => $from,
            'periodTo' => $to,
            'statuses' => $cases->pluck('status')->unique()->sort()->values(),
            'investigators' => $request->user()->isSupervisor()
                ? User::query()
                    ->whereRelation('role', 'role_name', Role::INVESTIGATOR)
                    ->approved()
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get()
                : collect(),
        ]);
    }
}
