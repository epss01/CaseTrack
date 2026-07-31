<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException(
                'Seeding demo accounts is refused outside local/testing (APP_ENV='.app()->environment().').'
            );
        }

        $this->call(RoleSeeder::class);

        $accounts = [
            ['role' => 'supervisor', 'username' => 'supervisor', 'first_name' => 'Office', 'last_name' => 'Supervisor'],
            ['role' => 'investigator', 'username' => 'investigator', 'first_name' => 'Field', 'last_name' => 'Investigator'],
        ];

        $credentials = [];

        foreach ($accounts as $account) {
            $password = Str::password(16);

            User::factory()->{$account['role']}()->create([
                'username' => $account['username'],
                'first_name' => $account['first_name'],
                'last_name' => $account['last_name'],
                'password' => $password,
            ]);

            $credentials[] = [$account['username'], $password];
        }

        $this->command?->table(['username', 'password'], $credentials);
        $this->command?->warn('Copy these now — they are not stored or shown again.');
    }
}
