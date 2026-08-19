<?php

namespace App\Services;

use App\Http\Requests\StoreCaseRequest;
use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Parses and validates a CSV/XLSX case import file. No database writes here —
 * CaseImportController::store() does those, once for each row this class
 * reports as ready.
 *
 * Scope is "Option B" (wiki/project/case-import.md): a case, its timeline,
 * and its victims/respondents/complainants, matching CHR's own convention of
 * recording several people on one case as extra rows sharing one Docket No.
 * Reusing StoreCaseRequest::intakeRules() means an imported case is validated
 * exactly as a hand-entered one would be — including requiring at least one
 * victim and one respondent — so the app's own /reports/export CSV, which
 * carries neither, is not by itself a valid import file for this scope.
 */
class CaseImportService
{
    /**
     * The eleven-column export contract's labels, minus Office Region (that
     * column renders the investigator's office_region, not a cases column,
     * so there is nothing on the case row to import it into). Frozen per
     * wiki/project/case-import.md — renaming one is a breaking change.
     *
     * @var array<string, string>
     */
    private const CASE_COLUMNS = [
        'Docket No.' => 'docket_no',
        'Title' => 'case_title',
        'Status' => 'status',
        'Investigator' => 'investigator',
        'Complexity Weight' => 'complexity_weight',
        'Date of Docket' => 'date_of_docket',
        '30-day Deadline' => 'extension_30_days',
        'ROP Submitted' => 'date_submission_rop',
        '120-day Deadline' => 'submission_120th_day',
        'FIR Submitted' => 'date_fir_submitted',
    ];

    /**
     * Columns the export never carries, needed only because Option B pulls
     * in the people export leaves out.
     *
     * @var array<string, string>
     */
    private const IMPORT_ONLY_COLUMNS = [
        'Incident Details' => 'incident_details',
        'Source of Information' => 'source_info',
        'Date Submitted To' => 'date_submitted_to',
        'Victim Name' => 'victim_name',
        'Victim Age' => 'victim_age',
        'Victim Status' => 'victim_status',
        'Victim Sector' => 'victim_sector',
        'Respondent Name' => 'respondent_name',
        'Respondent Age' => 'respondent_age',
        'Respondent Status' => 'respondent_status',
        'Respondent Sector' => 'respondent_sector',
        'Complainant Name' => 'complainant_name',
    ];

    private const REQUIRED_KEYS = ['docket_no', 'case_title', 'date_of_docket'];

    /**
     * The case-level labels this class reads, exposed only so
     * CaseImportServiceTest can assert they still match
     * ReportController::columns() — the two must not drift apart
     * (wiki/project/case-import.md: the export contract is frozen).
     *
     * @return list<string>
     */
    public static function caseColumnLabels(): array
    {
        return array_keys(self::CASE_COLUMNS);
    }

    /**
     * Case-level columns: the ones that must agree across every row sharing
     * one Docket No.. Everything not in this list is per-person and simply
     * accumulates instead.
     *
     * @var list<string>
     */
    private const CASE_LEVEL_KEYS = [
        'docket_no', 'case_title', 'status', 'investigator', 'complexity_weight',
        'date_of_docket', 'extension_30_days', 'date_submission_rop',
        'submission_120th_day', 'date_fir_submitted', 'incident_details',
        'source_info', 'date_submitted_to',
    ];

    /**
     * Cells normalized through PhpSpreadsheet's Shared\Date, since an XLSX
     * date-formatted cell reads back as a numeric serial rather than text.
     *
     * @var list<string>
     */
    private const DATE_KEYS = [
        'date_of_docket', 'date_submission_rop', 'extension_30_days',
        'submission_120th_day', 'date_fir_submitted',
    ];

    /**
     * C_j has no source value in the legacy spreadsheet at all — this is the
     * provisional weight a Supervisor is expected to review after import,
     * not a judgment this class makes.
     */
    private const DEFAULT_COMPLEXITY_WEIGHT = 3;

    /**
     * Friendlier field names for validation messages than Laravel's default
     * "the investigator id field" — reuses the column contract's own labels
     * rather than inventing a second vocabulary.
     *
     * @var array<string, string>
     */
    private const ATTRIBUTES = [
        'docket_no' => 'Docket No.',
        'case_title' => 'Title',
        'incident_details' => 'Incident Details',
        'source_info' => 'Source of Information',
        'complexity_weight' => 'Complexity Weight',
        'date_of_docket' => 'Date of Docket',
        'investigator_id' => 'Investigator',
        'status' => 'Status',
        'date_submission_rop' => 'ROP Submitted',
        'extension_30_days' => '30-day Deadline',
        'submission_120th_day' => '120-day Deadline',
        'date_fir_submitted' => 'FIR Submitted',
        'date_submitted_to' => 'Date Submitted To',
        'complainants.*.name' => 'Complainant Name',
        'victims.*.name' => 'Victim Name',
        'victims.*.age' => 'Victim Age',
        'victims.*.status' => 'Victim Status',
        'victims.*.sector' => 'Victim Sector',
        'respondents.*.name' => 'Respondent Name',
        'respondents.*.age' => 'Respondent Age',
        'respondents.*.status' => 'Respondent Status',
        'respondents.*.sector' => 'Respondent Sector',
    ];

    /**
     * Read a CSV or XLSX file into flat rows keyed by the internal column
     * names above, one PhpSpreadsheet reading path for both formats. No
     * grouping and no validation yet — see validate().
     *
     * Blank rows are dropped here (mirrors StoreCaseRequest::withoutBlankRows()).
     * A row missing a Docket No. is kept; validate() reports it, since "which
     * line" is more useful there than silently vanishing at parse time.
     *
     * @return list<array<string, mixed>>
     */
    public function parse(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $grid = $reader->load($path)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            throw new \RuntimeException(__('The file could not be read. Confirm it is a valid CSV or XLSX export.'), previous: $e);
        }

        if ($grid === []) {
            throw new \RuntimeException(__('The file has no rows.'));
        }

        $labelMap = self::CASE_COLUMNS + self::IMPORT_ONLY_COLUMNS;
        $keyByNormalizedLabel = [];
        foreach ($labelMap as $label => $key) {
            $keyByNormalizedLabel[$this->normalizeLabel($label)] = $key;
        }

        $header = array_shift($grid);
        $keyByColumn = array_map(
            fn ($label) => $keyByNormalizedLabel[$this->normalizeLabel((string) $label)] ?? null,
            $header
        );

        $missing = array_diff(self::REQUIRED_KEYS, array_filter($keyByColumn));
        if ($missing !== []) {
            $keyToLabel = array_flip($labelMap);
            throw new \RuntimeException(__('The file is missing required column(s): :labels', [
                'labels' => implode(', ', array_intersect_key($keyToLabel, array_flip($missing))),
            ]));
        }

        $rows = [];
        foreach ($grid as $i => $cells) {
            $row = ['_line' => $i + 2];
            foreach ($keyByColumn as $column => $key) {
                if ($key !== null) {
                    $row[$key] = $this->cleanCell($cells[$column] ?? null, $key);
                }
            }

            if ($this->isBlankRow($row)) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Group parsed rows by Docket No. into cases, validate each group
     * against the same rules StoreCaseRequest uses for hand entry, and split
     * the result into what would be created and what would be rejected.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{ready: list<array<string, mixed>>, errors: list<array{docket_no: ?string, lines: list<int>, messages: list<string>}>, defaulted_weight: int}
     */
    public function validate(array $rows): array
    {
        $groups = [];
        $errors = [];

        foreach ($rows as $row) {
            $docket = trim((string) ($row['docket_no'] ?? ''));

            if ($docket === '') {
                $errors[] = ['docket_no' => null, 'lines' => [$row['_line']], 'messages' => [__('Docket No. is required.')]];

                continue;
            }

            $groups[$docket][] = $row;
        }

        $usersByUsername = User::pluck('id', 'username');
        $usersByFullName = User::get(['id', 'first_name', 'last_name'])
            ->groupBy(fn (User $u) => Str::lower(trim("{$u->first_name} {$u->last_name}")));

        $ready = [];
        $defaultedWeight = 0;

        foreach ($groups as $docket => $groupRows) {
            $lines = array_column($groupRows, '_line');
            [$fields, $conflicts] = $this->collapseCaseFields($groupRows);

            if ($conflicts !== []) {
                $errors[] = ['docket_no' => $docket, 'lines' => $lines, 'messages' => $conflicts];

                continue;
            }

            [$investigatorId, $investigatorError] = $this->resolveInvestigator(
                $fields['investigator'] ?? null, $usersByUsername, $usersByFullName
            );

            $complexityWeight = $fields['complexity_weight'] ?? null;
            $defaulted = ! is_numeric($complexityWeight) || (int) $complexityWeight < 1 || (int) $complexityWeight > 5;
            $complexityWeight = $defaulted ? self::DEFAULT_COMPLEXITY_WEIGHT : (int) $complexityWeight;

            [$victims, $respondents, $complainants] = $this->collapsePeople($groupRows);

            $data = [
                'docket_no' => $docket,
                'case_title' => $fields['case_title'] ?? null,
                'incident_details' => $fields['incident_details'] ?? null,
                'source_info' => $fields['source_info'] ?? null,
                'complexity_weight' => $complexityWeight,
                'date_of_docket' => $fields['date_of_docket'] ?? null,
                'investigator_id' => $investigatorId,
                'status' => $fields['status'] ?? CaseModel::STATUS_DOCKETED,
                'date_submission_rop' => $fields['date_submission_rop'] ?? null,
                'extension_30_days' => $fields['extension_30_days'] ?? null,
                'submission_120th_day' => $fields['submission_120th_day'] ?? null,
                'date_fir_submitted' => $fields['date_fir_submitted'] ?? null,
                'date_submitted_to' => $fields['date_submitted_to'] ?? null,
                'complainants' => $complainants,
                'victims' => $victims,
                'respondents' => $respondents,
            ];

            $rules = StoreCaseRequest::intakeRules();
            unset($rules['investigator_id']);
            // Investigator resolution already ran above and reports its own
            // message; only re-check eligibility (active, approved, actually
            // an Investigator) once a candidate id was actually found.
            if ($investigatorId !== null) {
                $rules['investigator_id'] = ['integer', Role::assignableInvestigatorRule()];
            }
            $rules['status'] = CaseModel::statusRulesFor(new CaseModel);
            foreach (['date_submission_rop', 'extension_30_days', 'submission_120th_day', 'date_fir_submitted'] as $dateKey) {
                $rules[$dateKey] = array_merge(['nullable', 'date'], CaseTimeline::chronologyRules()[$dateKey]);
            }
            $rules['date_submitted_to'] = ['nullable', 'string', 'max:255'];

            $messages = ValidatorFacade::make($data, $rules, [], self::ATTRIBUTES)->errors()->all();

            if ($investigatorError !== null) {
                array_unshift($messages, $investigatorError);
            }

            if ($messages !== []) {
                $errors[] = ['docket_no' => $docket, 'lines' => $lines, 'messages' => $messages];

                continue;
            }

            if ($defaulted) {
                $defaultedWeight++;
            }

            $ready[] = $data;
        }

        return ['ready' => $ready, 'errors' => $errors, 'defaulted_weight' => $defaultedWeight];
    }

    /**
     * Collapse one Docket No. group's case-level fields: the first non-blank
     * value for a field wins, and a later row supplying a different
     * non-blank value for the same field is a conflict — CHR's own extra-row
     * convention is for adding people, not restating a case's own facts
     * differently row to row.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function collapseCaseFields(array $rows): array
    {
        $fields = [];
        $sourceLine = [];
        $conflicts = [];

        foreach ($rows as $row) {
            foreach (self::CASE_LEVEL_KEYS as $key) {
                $value = $row[$key] ?? null;
                $value = is_string($value) ? trim($value) : $value;

                if ($value === null || $value === '') {
                    continue;
                }

                if (! array_key_exists($key, $fields)) {
                    $fields[$key] = $value;
                    $sourceLine[$key] = $row['_line'];
                } elseif ((string) $fields[$key] !== (string) $value) {
                    $conflicts[] = __('Line :line disagrees with line :first on ":field" ("":value"" vs "":existing"").', [
                        'line' => $row['_line'],
                        'first' => $sourceLine[$key],
                        'field' => self::ATTRIBUTES[$key] ?? $key,
                        'value' => $value,
                        'existing' => $fields[$key],
                    ]);
                }
            }
        }

        return [$fields, $conflicts];
    }

    /**
     * Accumulate every named victim/respondent/complainant across a Docket
     * No. group's rows, dropping exact repeats (a spreadsheet row pasted
     * twice by mistake).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    private function collapsePeople(array $rows): array
    {
        $victims = [];
        $respondents = [];
        $complainants = [];

        foreach ($rows as $row) {
            if (($row['victim_name'] ?? '') !== '' && $row['victim_name'] !== null) {
                $victims[] = [
                    'name' => $row['victim_name'],
                    'age' => $row['victim_age'] ?? null,
                    'status' => $row['victim_status'] ?? null,
                    'sector' => $row['victim_sector'] ?? null,
                ];
            }

            if (($row['respondent_name'] ?? '') !== '' && $row['respondent_name'] !== null) {
                $respondents[] = [
                    'name' => $row['respondent_name'],
                    'age' => $row['respondent_age'] ?? null,
                    'status' => $row['respondent_status'] ?? null,
                    'sector' => $row['respondent_sector'] ?? null,
                ];
            }

            if (($row['complainant_name'] ?? '') !== '' && $row['complainant_name'] !== null) {
                $complainants[] = ['name' => $row['complainant_name']];
            }
        }

        $dedupe = fn (array $items) => array_values(array_map('unserialize', array_unique(array_map('serialize', $items))));

        return [$dedupe($victims), $dedupe($respondents), $dedupe($complainants)];
    }

    /**
     * Resolve the Investigator cell to a user id: exact username first (the
     * unambiguous key), then an exact "First Last" match. Two accounts
     * sharing a full name is reported rather than guessed at.
     *
     * @param  \Illuminate\Support\Collection<string, int>  $usersByUsername
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, User>>  $usersByFullName
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveInvestigator(?string $text, $usersByUsername, $usersByFullName): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return [null, __('Investigator is required.')];
        }

        if ($id = $usersByUsername->get($text)) {
            return [$id, null];
        }

        $matches = $usersByFullName->get(Str::lower($text)) ?? collect();

        return match ($matches->count()) {
            0 => [null, __('No account matches investigator ":name".', ['name' => $text])],
            1 => [$matches->first()->id, null],
            default => [null, __('":name" matches more than one account — use the username instead.', ['name' => $text])],
        };
    }

    /**
     * Strip the export's leading-apostrophe formula guard
     * (ReportController::neutralize()) and normalize a date cell to Y-m-d —
     * an XLSX date-formatted cell reads back as an Excel serial number
     * rather than text, so it needs converting before Laravel's "date" rule
     * can make sense of it. Parsing/formatting only, not the offset
     * arithmetic CLAUDE.md reserves for CaseDeadlineService.
     */
    private function cleanCell(mixed $value, string $key): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            if (strlen($value) > 1 && $value[0] === "'" && str_contains('=+-@', $value[1])) {
                $value = substr($value, 1);
            }
        }

        return in_array($key, self::DATE_KEYS, true) ? $this->normalizeDate($value) : $value;
    }

    /**
     * @return string the normalized "Y-m-d" date, or the original value
     *                 unchanged if it could not be parsed — left for the
     *                 "date" validation rule to reject with a clear message
     *                 rather than silently disappearing.
     */
    private function normalizeDate(mixed $value): string
    {
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        $string = trim((string) $value);

        try {
            return (new \DateTime($string))->format('Y-m-d');
        } catch (\Throwable) {
            return $string;
        }
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $key => $value) {
            if ($key !== '_line' && $value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeLabel(string $label): string
    {
        return Str::of($label)->squish()->lower()->toString();
    }
}
