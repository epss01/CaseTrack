<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the roles referenced by users.role_id.
     */
    public function run(): void
    {
        foreach (Role::ALL as $roleName) {
            Role::firstOrCreate(['role_name' => $roleName]);
        }
    }
}
