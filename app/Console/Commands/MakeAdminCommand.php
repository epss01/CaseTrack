<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * The only way an Admin account gets created.
 *
 * Deliberately no route and no view: Admin accounts are not created through
 * public registration (Role::selectableRoleRule() enforces that) or through
 * the account-management screen (UserAccountController::updateRole() refuses
 * Admin as a destination) — this command is the entire provisioning surface.
 * Run on the machine, by whoever already has shell access to it.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'make:admin';

    protected $description = 'Create an Admin account';

    public function handle(): int
    {
        $username = $this->ask('Username');
        $firstName = $this->ask('First name');
        $lastName = $this->ask('Last name');
        $password = $this->secret('Password (min 8 characters)');
        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'password' => Hash::make($password),
            'role_id' => Role::firstOrCreate(['role_name' => Role::ADMIN])->id,
            'registration_status' => User::REGISTRATION_APPROVED,
            'is_active' => true,
        ]);

        $this->info("Admin account '{$username}' created.");

        return self::SUCCESS;
    }
}
