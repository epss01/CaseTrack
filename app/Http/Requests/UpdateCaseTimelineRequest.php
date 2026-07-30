<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCaseTimelineRequest extends FormRequest
{
    /**
     * Authorization is handled by CaseModelPolicy::update() via the explicit
     * authorize() call in CaseTimelineController.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Blank date inputs post as empty strings; normalise them to null so the
     * nullable rules apply and the columns are cleared rather than rejected.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(
            collect($this->only(array_keys($this->rules())))
                ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
                ->all()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The date the case was docketed opens the timeline, so unlike the
            // milestones below it can never be cleared.
            'date_of_docket' => ['required', 'date'],

            'date_submission_rop' => ['nullable', 'date'],
            'extension_30_days' => ['nullable', 'date'],
            'submission_60th_day' => ['nullable', 'date'],
            'submission_120th_day' => ['nullable', 'date'],
            'target_date_fir' => ['nullable', 'date'],
            'date_fir_submitted' => ['nullable', 'date'],

            // Despite the column name this records who the report went to,
            // not a date.
            'date_submitted_to' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date_of_docket' => __('date of docket'),
            'date_submission_rop' => __('date of submission (ROP)'),
            'extension_30_days' => __('30-day extension'),
            'submission_60th_day' => __('submission (60th day)'),
            'submission_120th_day' => __('submission (120th day)'),
            'target_date_fir' => __('target date (FIR)'),
            'date_fir_submitted' => __('date FIR submitted'),
            'date_submitted_to' => __('submitted to'),
        ];
    }
}
