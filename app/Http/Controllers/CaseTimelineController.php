<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCaseTimelineRequest;
use App\Models\AuditLog;
use App\Models\CaseModel;
use Illuminate\Support\Facades\DB;

/**
 * The timeline-tracking milestones for a case.
 *
 * Intake opens the timeline with the date of docket; the milestones that
 * follow (ROP submission, the 30-day extension, the 60th/120th day, the FIR)
 * only become known weeks or months later, so they are set here instead.
 *
 * These are custom actions, so authorizeResource() does not apply and each
 * one authorizes explicitly. The ability is 'update' on the case itself:
 * whoever may edit the case may set its timeline.
 */
class CaseTimelineController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the Set Timeline form.
     */
    public function edit(CaseModel $case)
    {
        $this->authorize('update', $case);

        return view('cases.timeline', [
            'case' => $case,
            'timeline' => $case->timeline,
        ]);
    }

    /**
     * Persist the timeline.
     *
     * updateOrCreate covers cases docketed before intake started writing a
     * timeline row, which would otherwise have nothing to update.
     *
     * Audited against the parent case: audit_logs is keyed to cases, and the
     * timeline is 1:1 with one anyway.
     */
    public function update(UpdateCaseTimelineRequest $request, CaseModel $case)
    {
        $this->authorize('update', $case);

        DB::transaction(function () use ($request, $case) {
            $case->timeline()->updateOrCreate(
                ['case_id' => $case->id],
                $request->validated()
            );

            AuditLog::record($request->user(), $case, AuditLog::ACTION_TIMELINE_UPDATE);
        });

        return redirect()
            ->route('cases.show', $case)
            ->with('status', __('Timeline updated.'));
    }
}
