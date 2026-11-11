<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\CaseModel;
use App\Models\Role;
use App\Models\User;
use App\Services\CaseAnalyticsService;
use App\Services\CaseProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AnalyticsController extends Controller
{
    public function caseProfiles(Request $request, CaseProfileService $profiles): Renderable
    {
        $this->authorize('viewAny', CaseModel::class);
        $today = CarbonImmutable::today();
        $dates = Validator::make([
            'from' => $request->query('from') ?? $today->startOfYear()->toDateString(),
            'to' => $request->query('to') ?? $today->toDateString(),
        ], [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ])->validate();

        $cases = DB::transaction(fn () => CaseModel::query()
            ->visibleTo($request->user())
            ->whereHas('timeline', fn ($timeline) => $timeline
                ->whereDate('date_fir_submitted', '>=', $dates['from'])
                ->whereDate('date_fir_submitted', '<=', $dates['to']))
            ->select(['id', 'docket_no', 'complexity_weight'])
            ->with('timeline:case_id,date_of_docket,date_fir_submitted')
            ->withCount(['victims', 'respondents', 'complainants'])
            ->get());

        return view('reports.case-profiles', $dates + ['report' => $profiles->report($cases)]);
    }

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
