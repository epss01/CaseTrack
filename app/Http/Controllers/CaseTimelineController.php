<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCaseTimelineRequest;
use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Services\CaseDeadlineService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    public function edit(CaseModel $case, CaseDeadlineService $deadlines)
    {
        $this->authorize('update', $case);

        $case->timeline?->setRelation('case', $case);

        return view('cases.timeline', [
            'case' => $case,
            'timeline' => $case->timeline,
            'tortureMilestone' => $deadlines->statusesFor($case->timeline)[CaseDeadlineService::SIXTIETH_DAY] ?? null,
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
            $fresh = CaseModel::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $this->authorize('update', $fresh);
            $validated = $request->validated();
            if ($fresh->lock_version !== (int) $validated['lock_version']) {
                throw ValidationException::withMessages([
                    'lock_version' => __('This case was updated by someone else while you were editing. Reload the page to see the latest version before saving again.'),
                ]);
            }

            $previousExtension = $fresh->timeline?->extension_60th_days?->format('Y-m-d');
            $granted = $validated['torture_extension_granted'] ?? ($previousExtension !== null);
            if ($granted && $fresh->is_torture_case !== true) {
                throw ValidationException::withMessages([
                    'torture_extension_granted' => __('An external extension can only be recorded for a confirmed torture case. Clear any mistaken extension first.'),
                ]);
            }
            $docketChanged = $fresh->timeline?->date_of_docket?->format('Y-m-d') !== CarbonImmutable::parse($validated['date_of_docket'])->format('Y-m-d');
            $extension = $previousExtension;
            if (array_key_exists('torture_extension_granted', $validated) || $docketChanged) {
                $extension = $granted
                    ? CaseDeadlineService::deadlineOn($validated['date_of_docket'], CaseDeadlineService::EXTENDED_TORTURE_DAYS)->format('Y-m-d')
                    : null;
            }
            if ($extension !== $previousExtension && blank($validated['extension_note'] ?? null)) {
                throw ValidationException::withMessages([
                    'extension_note' => __('Enter the external approval reference or an explanatory note for recording, removing or recalculating the extension.'),
                ]);
            }
            $fresh->timeline()->updateOrCreate(
                ['case_id' => $fresh->id],
                collect($validated)->except(['lock_version', 'reason', 'torture_extension_granted', 'extension_note'])->all()
                    + ['extension_60th_days' => $extension]
            );
            $fresh->lock_version++;
            $fresh->save();

            AuditLog::record($request->user(), $fresh, AuditLog::ACTION_TIMELINE_UPDATE);
            if ($extension !== $previousExtension) {
                $action = $extension === null ? AuditLog::ACTION_TORTURE_EXTENSION_REMOVED
                    : ($previousExtension === null ? AuditLog::ACTION_TORTURE_EXTENSION_RECORDED : AuditLog::ACTION_TORTURE_EXTENSION_RECALCULATED);
                AuditLog::record($request->user(), $fresh, $action, notes: json_encode([
                    'previous_deadline' => $previousExtension,
                    'deadline' => $extension,
                    'note' => $validated['extension_note'],
                ], JSON_THROW_ON_ERROR));
            }
            if ($request->user()->isSupervisor()) {
                AuditLog::record($request->user(), $fresh, AuditLog::ACTION_TIMELINE_EDIT_ON_BEHALF, notes: $validated['reason']);
            }
        });

        return redirect()
            ->route('cases.show', $case)
            ->with('status', __('Timeline updated.'));
    }
}
