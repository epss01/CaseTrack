<?php

namespace App\Http\Requests;

use App\Models\CaseModel;
use Illuminate\Foundation\Http\FormRequest;

class ChangeCaseClosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeClosure', $this->route('case'));
    }

    public function rules(): array
    {
        return [
            'status' => CaseModel::statusRulesFor($this->route('case')),
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
