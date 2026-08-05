<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rotates one or more accounts off a known/shared password, from the shell.
 *
 * Built for the seeded factory/demo accounts (UserFactory::definition() hands
 * every factory-made user the same static Hash::make('password')) but works
 * on any username. No route, no policy check: there is no authenticated
 * request here, so shell access is the gate — same posture as
 * feature/admin-role's make:admin.
 *
 * Rotating the password alone would not revoke an already-open session:
 * SESSION_DRIVER=database and no AuthenticateSession middleware is
 * registered, so this also clears the remember-me token and deletes the
 * account's session rows.
 */
class RotatePasswordCommand extends Command
{
    protected $signature = 'users:rotate-password
        {username?* : Usernames to rotate}
        {--all : Rotate every account in the users table}
        {--actor= : Username of the operator, recorded as the audit entry\'s actor}';

    protected $description = 'Generate a new random password for one or more accounts, ending their sessions';

    public function handle(): int
    {
        $actorUsername = $this->option('actor');

        if (! $actorUsername) {
            $this->error('--actor=<username> is required, so the audit entry records who ran this.');

            return self::FAILURE;
        }

        $actor = User::where('username', $actorUsername)->first();

        if (! $actor) {
            $this->error("No user found with username '{$actorUsername}'.");

            return self::FAILURE;
        }

        $usernames = $this->argument('username');

        if ($this->option('all')) {
            $users = User::all();
        } elseif ($usernames) {
            $users = User::whereIn('username', $usernames)->get();

            $missing = array_diff($usernames, $users->pluck('username')->all());
            foreach ($missing as $username) {
                $this->error("No user found with username '{$username}', skipping.");
            }
        } else {
            $this->error('Pass one or more usernames, or --all.');

            return self::FAILURE;
        }

        if ($users->isEmpty()) {
            $this->error('No matching accounts to rotate.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($users as $user) {
            $newPassword = Str::password(20);

            DB::transaction(function () use ($user, $newPassword, $actor) {
                $user->password = $newPassword;
                $user->setRememberToken(Str::random(60));
                $user->save();

                DB::table('sessions')->where('user_id', $user->id)->delete();

                AuditLog::record($actor, null, AuditLog::ACTION_ACCOUNT_PASSWORD_RESET, $user);
            });

            $rows[] = [$user->username, $newPassword];
        }

        $this->table(['username', 'new password'], $rows);
        $this->warn('Copy these now — they are not stored or shown again.');

        return self::SUCCESS;
    }
}
