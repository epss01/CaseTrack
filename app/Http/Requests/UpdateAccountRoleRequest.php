<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRoleRequest extends FormRequest
{
    /**
     * Authorization is handled by the role:Admin middleware on the route,
     * plus UserAccountController::updateRole()'s own self-action and
     * Admin-target guards.
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
            // Shared with the registration form: a role change can only land
            // on Investigator or Supervisor, never Admin.
            'role_id' => ['required', Role::selectableRoleRule()],
        ];
    }
}
