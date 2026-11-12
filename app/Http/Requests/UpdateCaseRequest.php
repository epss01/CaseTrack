<?php

namespace App\Http\Requests;

use App\Models\CaseModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseRequest extends FormRequest
{
    /**
     * Authorization is handled by CaseModelPolicy::update() in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['victims', 'respondents', 'complainants'] as $group) {
            if (is_array($rows = $this->input($group))) {
                $rows = array_map(function ($row) {
                    if (is_array($row) && isset($row['_remove']) && is_bool($row['_remove'])) {
                        $row['_remove'] = (int) $row['_remove'];
                    }

                    return $row;
                }, $rows);
                $this->merge([$group => array_filter($rows, function ($row) {
                    // Ignore untouched new rows, but never hide malformed input or an existing ID.
                    if (! is_array($row) || filled($row['id'] ?? null) || filled($row['_remove'] ?? null)) {
                        return true;
                    }

                    return collect($row)->contains(fn ($value) => ! is_scalar($value) && $value !== null || filled($value));
                })]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $case = $this->route('case');
        $onBehalf = $this->user()->isSupervisor() && $case->investigator_id !== $this->user()->id;

        $rules = [
            'case_title' => ['required', 'string', 'max:255'],
            'incident_details' => ['required', 'string'],
            'source_info' => ['nullable', 'string', 'max:255'],
            'status' => CaseModel::statusRulesFor($case),
            'complexity_weight' => CaseModel::complexityWeightRules(),
            'lock_version' => ['required', 'integer', 'min:0'],
            'reason' => $onBehalf ? ['required', 'string', 'max:500'] : ['nullable', 'string', 'max:500'],
            'is_torture_case' => ['sometimes', 'nullable', 'boolean'],
        ];

        foreach (['victims', 'respondents', 'complainants'] as $group) {
            $columns = $group === 'complainants' ? 'name' : 'name,age,status,sector';
            $rules[$group] = ['sometimes', 'array'];
            $rules[$group.'.*'] = ['array:id,_remove,'.$columns];
            $rules[$group.'.*.id'] = ['nullable', 'required_if:'.$group.'.*._remove,1', 'integer', 'distinct', Rule::exists($case->{$group}()->getRelated()->getTable(), 'id')->where('case_id', $case->id)];
            $rules[$group.'.*._remove'] = ['sometimes', 'boolean'];
        }
        foreach (StoreCaseRequest::peopleRules() as $field => $fieldRules) {
            $group = explode('.', $field)[0];
            $rules[$field] = array_merge(['exclude_if:'.$group.'.*._remove,1'], $fieldRules);
        }

        return $rules;
    }
}
