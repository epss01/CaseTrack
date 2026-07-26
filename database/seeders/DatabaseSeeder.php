<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        User::factory()->supervisor()->create([
            'username' => 'supervisor',
            'first_name' => 'Office',
            'last_name' => 'Supervisor',
        ]);

        User::factory()->investigator()->create([
            'username' => 'investigator',
            'first_name' => 'Field',
            'last_name' => 'Investigator',
        ]);
    }
}
