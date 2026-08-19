<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadCaseImportRequest;
use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use App\Services\CaseImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Bulk case intake from a CSV/XLSX file — Option B (case + timeline +
 * people), the write-side mirror of /reports/export. Supervisor-only
 * (role:Supervisor route middleware): import creates cases and assigns
 * investigators, which is case data, so — unlike /workload or /admin/* — it
 * is not something the Admin role touches (CLAUDE.md, Roles / access rules).
 *
 * Three steps, no per-case decision anywhere in them for a policy class to
 * make: upload, a dry-run preview nothing is written from, and a commit that
 * re-validates before writing. Matches the app's existing bias toward
 * confirmation before an irreversible action (maker-checker closure, the
 * delete confirmation page).
 */
class CaseImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function create()
    {
        return view('cases.import.create');
    }

    /**
     * Parse and validate the uploaded file, but write nothing. The file is
     * stashed on the private disk under a name fixed to the acting user so a
     * second preview overwrites rather than accumulates.
     *
     * ponytail: no scheduled sweep for a preview a Supervisor uploads and
     * never commits. The fixed-per-user filename caps the mess at one stale
     * file per user rather than one per attempt — add a sweep if that
     * proves not to be enough.
     */
    public function preview(UploadCaseImportRequest $request, CaseImportService $importer)
    {
        $upload = $request->file('file');
        $path = $upload->storeAs('imports', "user-{$request->user()->id}.".$upload->getClientOriginalExtension());

        try {
            $result = $importer->validate($importer->parse(Storage::path($path)));
        } catch (\RuntimeException $e) {
            Storage::delete($path);

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        session([
            'case_import.path' => $path,
            'case_import.original_name' => $upload->getClientOriginalName(),
        ]);

        // Ready rows only carry investigator_id; resolved here so the
        // preview table can show a name without an N+1 lookup per row.
        $investigatorNames = User::whereIn('id', collect($result['ready'])->pluck('investigator_id'))
            ->get()
            ->pluck('full_name', 'id');

        return view('cases.import.preview', [
            'result' => $result,
            'fileName' => $upload->getClientOriginalName(),
            'investigatorNames' => $investigatorNames,
        ]);
    }

    /**
     * Re-parses and re-validates the stashed file rather than trusting the
     * preview's result — a docket number or an account could have changed in
     * the meantime, and creating from a stale read would be exactly the kind
     * of silent drift the dry-run step exists to prevent. Any row that
     * passed preview but fails here simply shows up in the rejected count
     * below instead of being created.
     */
    public function store(Request $request, CaseImportService $importer)
    {
        $path = session('case_import.path');

        if (! $path || ! Storage::exists($path)) {
            return redirect()->route('cases.import.create')
                ->withErrors(['file' => __('Nothing to import — upload a file first.')]);
        }

        try {
            $result = $importer->validate($importer->parse(Storage::path($path)));
        } catch (\RuntimeException $e) {
            Storage::delete($path);
            session()->forget(['case_import.path', 'case_import.original_name']);

            return redirect()->route('cases.import.create')->withErrors(['file' => $e->getMessage()]);
        }

        $created = DB::transaction(function () use ($result, $request) {
            $cases = [];

            foreach ($result['ready'] as $row) {
                $case = CaseModel::create([
                    'docket_no' => $row['docket_no'],
                    'case_title' => $row['case_title'],
                    'incident_details' => $row['incident_details'],
                    'source_info' => $row['source_info'],
                    'investigator_id' => $row['investigator_id'],
                    'complexity_weight' => $row['complexity_weight'],
                    'status' => $row['status'],
                ]);

                $case->complainants()->createMany($row['complainants']);
                $case->victims()->createMany($row['victims']);
                $case->respondents()->createMany($row['respondents']);

                $case->timeline()->create([
                    'date_of_docket' => $row['date_of_docket'],
                    'date_submission_rop' => $row['date_submission_rop'],
                    'extension_30_days' => $row['extension_30_days'],
                    'submission_120th_day' => $row['submission_120th_day'],
                    'date_fir_submitted' => $row['date_fir_submitted'],
                    'date_submitted_to' => $row['date_submitted_to'],
                ]);

                AuditLog::record($request->user(), $case, AuditLog::ACTION_CREATE);

                $cases[] = $case;
            }

            if ($cases !== []) {
                AuditLog::record($request->user(), null, AuditLog::ACTION_IMPORTED);
            }

            return $cases;
        });

        Storage::delete($path);
        session()->forget(['case_import.path', 'case_import.original_name']);

        $status = trans_choice(':count case imported.|:count cases imported.', count($created), ['count' => count($created)]);

        if ($result['errors'] !== []) {
            $status .= ' '.trans_choice(
                ':count row rejected — see the preview to retry.|:count rows rejected — see the preview to retry.',
                count($result['errors']),
                ['count' => count($result['errors'])]
            );
        }

        return redirect()->route('cases.index')->with('status', $status);
    }
}
