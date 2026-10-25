<?php

namespace App\Http\Requests;

use App\Models\CaseModel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCaseRequest extends FormRequest
{
    /**
     * Authorization is handled by CaseModelPolicy::update() in the controller.
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
        $case = $this->route('case');
        $onBehalf = $this->user()->isSupervisor() && $case->investigator_id !== $this->user()->id;

        return [
            'case_title' => ['required', 'string', 'max:255'],
            'incident_details' => ['required', 'string'],
            'source_info' => ['nullable', 'string', 'max:255'],
            'status' => CaseModel::statusRulesFor($case),
            'complexity_weight' => CaseModel::complexityWeightRules(),
            'lock_version' => ['required', 'integer', 'min:0'],
            'reason' => $onBehalf ? ['required', 'string', 'max:500'] : ['nullable', 'string', 'max:500'],
        ];
    }
}
