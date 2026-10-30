<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCaseImportRequest extends FormRequest
{
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
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => __('import file'),
        ];
    }
}
