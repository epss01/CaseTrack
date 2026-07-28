<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformanceRatingRequest extends FormRequest
{
    /**
     * Authorization is handled by the role:Supervisor middleware on the route.
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
