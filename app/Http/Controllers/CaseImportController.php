<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadCaseImportRequest;
use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\Role;
use App\Models\User;
use App\Services\CaseImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bulk case intake from a CSV/XLSX file — Option B (case + timeline +
 * people). Both case-handling roles may import; Investigators are confined
 * to their own caseload by the shared validator. Admins cannot import.
 *
 * Three steps, no per-case decision anywhere in them for a policy class to
 * make: upload, a dry-run preview nothing is written from, and a commit that
 * re-validates before writing. Matches the app's existing bias toward
 * confirmation before an irreversible action (the delete confirmation page).
 */
class CaseImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:create,App\Models\CaseModel');
        $this->middleware('submission:create')->only('store');
    }

    public function create(Request $request)
    {
        return view('cases.import.create', [
            'investigators' => $request->user()->isSupervisor()
                ? User::whereRelation('role', 'role_name', Role::INVESTIGATOR)->approved()
                    ->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get()
                : collect(),
        ]);
    }

    /**
     * Parse and validate the uploaded file, but write nothing. The file is
     * stashed on the private disk under an immutable preview identifier.
     *
     * shortcut: no scheduled sweep for a preview a user uploads and
     * never commits. A new preview removes that session's previous upload;
     * add a sweep if abandoned browser sessions accumulate files.
     */
    public function preview(UploadCaseImportRequest $request, CaseImportService $importer)
    {
        $upload = $request->file('file');
        if ($this->ownsStagedFile($request)) {
            Storage::delete(session('case_import.path'));
        }
        session()->forget('case_import');
        $fallback = $request->filled('investigator_id') ? $request->integer('investigator_id') : null;
        $previewId = (string) Str::uuid();
        $path = $upload->storeAs('imports', "user-{$request->user()->id}-{$previewId}.".strtolower($upload->getClientOriginalExtension()));

        try {
            $result = $importer->validate($importer->parse(Storage::path($path)), $request->user(), $fallback);
        } catch (\RuntimeException $e) {
            Storage::delete($path);

            return back()->withErrors(['file' => $e->getMessage()])
                ->withInput($request->only('investigator_id'));
        }

        session([
            'case_import.path' => $path,
            'case_import.id' => $previewId,
            'case_import.original_name' => $upload->getClientOriginalName(),
            'case_import.user_id' => $request->user()->id,
            'case_import.investigator_id' => $fallback,
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

        if ($path) {
            abort_unless($this->ownsStagedFile($request), 403);
        }

        if (! $path || ! Storage::exists($path)) {
            return redirect()->route('cases.import.create')
                ->withErrors(['file' => __('Nothing to import — upload a file first.')]);
        }

        abort_unless($request->input('_import_id') === session('case_import.id'), 409,
            __('This import preview has been replaced. Upload and review the file again.'));

        $fallback = session('case_import.investigator_id');
        abort_if($fallback !== null && ! $request->user()->isSupervisor(), 403);

        try {
            $result = $importer->validate($importer->parse(Storage::path($path)), $request->user(), $fallback);
        } catch (\RuntimeException $e) {
            Storage::delete($path);
            session()->forget('case_import');

            return redirect()->route('cases.import.create')->withErrors(['file' => $e->getMessage()]);
        }

        if ($result['ready'] === []) {
            return redirect()->route('cases.import.create')->withErrors([
                'file' => __('No cases are ready to import. Review the file and assignee eligibility before retrying.'),
            ]);
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
                    'date_extension_30_days_requested' => $row['date_extension_30_days_requested'],
                    'date_submission_120th_day_rop' => $row['date_submission_120th_day_rop'],
                    'date_submission_60th_day_rorp' => $row['date_submission_60th_day_rorp'],
                    'target_date_fir' => $row['target_date_fir'],
                ]);

                AuditLog::record($request->user(), $case, AuditLog::ACTION_CREATE);

                $cases[] = $case;
            }

            if ($cases !== []) {
                AuditLog::record($request->user(), null, AuditLog::ACTION_IMPORTED);
            }

            return $cases;
        });

        DB::afterCommit(function () use ($path) {
            Storage::delete($path);
            session()->forget('case_import');
        });

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

    private function ownsStagedFile(Request $request): bool
    {
        $id = session('case_import.id');

        return is_string($id) && Str::isUuid($id)
            && session('case_import.user_id') === $request->user()->id
            && in_array(session('case_import.path'), array_map(
                fn ($extension) => "imports/user-{$request->user()->id}-{$id}.{$extension}",
                ['csv', 'txt', 'xlsx']
            ), true);
    }
}
