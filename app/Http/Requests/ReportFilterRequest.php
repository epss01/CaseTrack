<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The filters every report accepts.
 *
 * One request class for the listing and the CSV rather than one each, so the
 * two cannot drift into accepting different things — the same reason
 * Role::assignableInvestigatorRule() is shared by intake and reassignment.
 */
class ReportFilterRequest extends FormRequest
{
    /**
     * Authorization is handled by the role:Investigator,Supervisor middleware
     * on the route, and the results are scoped by CaseModel::scopeVisibleTo().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],

            // Reused rather than restated: a case can only be assigned to
            // someone holding the Investigator role, so only such a person can
            // be worth filtering by.
            //
            // This is a convenience, not a boundary. An investigator who passes
            // a colleague's id still meets scopeVisibleTo() first and gets an
            // empty set back, never the colleague's cases.
            'investigator_id' => ['nullable', Role::assignableInvestigatorRule()],

            // Free text on purpose. cases.status has no ratified vocabulary —
            // three named constants, two more values circulating only in the
            // factory and seeder — so a Rule::in here would invent one.
            'status' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from' => __('date from'),
            'to' => __('date to'),
            'investigator_id' => __('investigator'),
        ];
    }

    /**
     * The filters, ready for CaseModel::scopeFilteredBy().
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter($this->validated(), fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Whether a date range was asked for.
     *
     * The listing needs to know: a date filter reads through to
     * case_timelines, so cases with no timeline row fall out of the results
     * and have to be reported rather than silently dropped.
     */
    public function hasDateFilter(): bool
    {
        return isset($this->filters()['from']) || isset($this->filters()['to']);
    }
}
