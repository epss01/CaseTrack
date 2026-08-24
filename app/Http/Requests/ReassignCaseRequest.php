<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignCaseRequest extends FormRequest
{
    /**
     * Authorization is handled by CaseModelPolicy::reassign() via the explicit
     * authorize() call in CaseController.
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
            // Correcting a docket number post-entry must still not collide
            // with another case, but keeping the current one is fine.
            'docket_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cases', 'docket_no')->ignore($this->route('case')),
            ],

            'investigator_id' => ['required', 'integer', Role::assignableInvestigatorRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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
        ];
    }
}
