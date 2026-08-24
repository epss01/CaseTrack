<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformanceRatingRequest extends FormRequest
{
    /**
     * role:Supervisor middleware on the route covers who may reach this
     * action; this covers what the {investigator} route-model-bound target
     * may be. Unlike ReassignCaseRequest's investigator_id (a posted
     * value, validated through Role::assignableInvestigatorRule()), the
     * target here is bound straight from the URL to any User row — without
     * this check a supervisor could set a performance_rating, a value that
     * only means anything for the WCS formula's P_i, on another supervisor
     * or an admin.
     */
    public function authorize(): bool
    {
        return $this->route('investigator')?->isInvestigator() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // P_i is normalized to 0.1-1.0. Zero is excluded deliberately: at
            // P_i = 0 the (2 - P_i) factor would double an investigator's
            // score, and a rating of "none" is not the same as a rating of
            // "worst".
            'performance_rating' => ['required', 'numeric', 'between:0.1,1.0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'performance_rating' => __('performance rating'),
        ];
    }
}
