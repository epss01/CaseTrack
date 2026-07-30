<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountPasswordRequest extends FormRequest
{
    /**
     * Authorization is handled by the role:Admin middleware on the route,
     * plus UserAccountController::updatePassword()'s own self-action guard.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same shape as registration's password rule.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
