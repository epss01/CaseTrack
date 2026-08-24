<?php

namespace App\Http\Requests;

use App\Models\CaseModel;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaseRequest extends FormRequest
{
    /**
     * Authorization is handled by CaseModelPolicy::create() via the
     * authorizeResource() call in CaseController.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop blank repeater rows and pin the assignment before validating.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'complainants' => $this->withoutBlankRows($this->input('complainants')),
            'victims' => $this->withoutBlankRows($this->input('victims')),
            'respondents' => $this->withoutBlankRows($this->input('respondents')),
        ]);

        // An investigator always files under their own name; only a
        // supervisor chooses who a case is assigned to.
        if (! $this->user()->isSupervisor()) {
            $this->merge(['investigator_id' => $this->user()->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return static::intakeRules();
    }

    /**
     * The rules a new case must satisfy, shared with CaseImportService so an
     * imported case is validated identically to one entered by hand — the
     * whole reason Option B import reuses this rather than a looser,
     * import-only rule set (wiki/project/case-import.md).
     *
     * @return array<string, mixed>
     */
    public static function intakeRules(): array
    {
        return [
            'docket_no' => ['required', 'string', 'max:255', 'unique:cases,docket_no'],
            'case_title' => ['required', 'string', 'max:255'],
            'incident_details' => ['required', 'string'],
            'source_info' => ['nullable', 'string', 'max:255'],

            // C_j in the Workload Capacity Score — captured here because the
            // score is meaningless if every case is docketed weightless.
            'complexity_weight' => CaseModel::complexityWeightRules(),

            // Required, not defaulted (CHR-Answers-2026-08-01, item 3): the
            // 60-day RORP milestone binds torture cases only, and this has to
            // be determined at docketing, not guessed later. See
            // CaseModel::$fillable and the is_torture_case migration for why
            // pre-existing cases are nullable instead.
            'is_torture_case' => ['required', 'boolean'],

            // Opens the case timeline. The remaining milestone dates are not
            // known at intake and are filled in later via the timeline form.
            'date_of_docket' => ['required', 'date'],

            // Must be a user who actually holds the Investigator role.
            'investigator_id' => ['required', 'integer', Role::assignableInvestigatorRule()],

            // Optional: a case opened from a media report or on the
            // Commission's own initiative has no complainant to name.
            'complainants' => ['nullable', 'array'],
            'complainants.*.name' => ['required', 'string', 'max:255'],

            'victims' => ['required', 'array', 'min:1'],
            'victims.*.name' => ['required', 'string', 'max:255'],
            'victims.*.age' => ['nullable', 'integer', 'min:0', 'max:255'],
            'victims.*.status' => ['required', 'string', 'max:255'],
            'victims.*.sector' => ['required', 'string', 'max:255'],

            'respondents' => ['required', 'array', 'min:1'],
            'respondents.*.name' => ['required', 'string', 'max:255'],
            'respondents.*.age' => ['nullable', 'integer', 'min:0', 'max:255'],
            'respondents.*.status' => ['nullable', 'string', 'max:255'],
            'respondents.*.sector' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'victims.required' => __('Record at least one victim.'),
            'victims.min' => __('Record at least one victim.'),
            'respondents.required' => __('Record at least one respondent.'),
            'respondents.min' => __('Record at least one respondent.'),
            'investigator_id.required' => __('Choose the investigator this case is assigned to.'),
            'investigator_id.exists' => __('The selected user is not an investigator who can be assigned cases.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'docket_no' => __('docket number'),
            'case_title' => __('case title'),
            'incident_details' => __('incident details'),
            'source_info' => __('source of information'),
            'complexity_weight' => __('complexity weight'),
            'is_torture_case' => __('torture case'),
            'date_of_docket' => __('date of docket'),
            'complainants.*.name' => __('complainant name'),
            'victims.*.name' => __('victim name'),
            'victims.*.age' => __('victim age'),
            'victims.*.status' => __('victim status'),
            'victims.*.sector' => __('victim sector'),
            'respondents.*.name' => __('respondent name'),
            'respondents.*.age' => __('respondent age'),
            'respondents.*.status' => __('respondent status'),
            'respondents.*.sector' => __('respondent sector'),
        ];
    }

    /**
     * Remove repeater rows the user added but left entirely empty, so an
     * untouched extra row is ignored rather than reported as invalid.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function withoutBlankRows(mixed $rows): array
    {
        return collect(is_array($rows) ? $rows : [])
            ->reject(fn ($row) => collect((array) $row)
                ->every(fn ($value) => $value === null || trim((string) $value) === ''))
            ->values()
            ->all();
    }
}
