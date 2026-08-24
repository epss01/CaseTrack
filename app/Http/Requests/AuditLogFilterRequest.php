<?php

namespace App\Http\Requests;

use App\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The filters the audit log viewer accepts.
 *
 * Modelled on ReportFilterRequest — one request class, filters() feeding
 * AuditLog::scopeFilteredBy() the way ReportFilterRequest feeds
 * CaseModel::scopeFilteredBy().
 */
class AuditLogFilterRequest extends FormRequest
{
    /**
     * Authorization is handled by the role:Admin middleware on the route.
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
            'user_id' => ['nullable', 'integer', 'exists:users,id'],

            // Unlike cases.status, this vocabulary is ratified — every value
            // record() accepts is named in AuditLog::ACTIONS, so unlike
            // ReportFilterRequest's free-text status filter, this one can be
            // constrained.
            'action' => ['nullable', 'string', Rule::in(AuditLog::ACTIONS)],

            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => __('acting user'),
            'from' => __('date from'),
            'to' => __('date to'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter($this->validated(), fn ($value) => $value !== null && $value !== '');
    }
}
