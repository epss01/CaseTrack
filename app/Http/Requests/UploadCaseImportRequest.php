<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UploadCaseImportRequest extends FormRequest
{
    public const MAX_SIZE_KB = 20480;

    /**
     * Routes enforce case-handling roles; the controller enforces the
     * case-creation policy and the importer validates assignment per case.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * extensions:, not mimes: — a CSV's detected MIME type is unreliable
     * across browsers and OSes, while the extension is exactly what
     * CaseImportService::parse() dispatches its reader on.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:'.self::MAX_SIZE_KB],
            'investigator_id' => $this->user()->isSupervisor()
                ? ['nullable', 'integer', Role::assignableInvestigatorRule()]
                : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => __('import file'),
            'investigator_id' => __('fallback investigator'),
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => __('The import file must not exceed :size MB.', ['size' => self::MAX_SIZE_KB / 1024]),
            'file.uploaded' => __('The upload failed. Choose a file up to :size MB. If it is smaller, ask the administrator to check the server upload limit.', ['size' => self::MAX_SIZE_KB / 1024]),
        ];
    }
}
