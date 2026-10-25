<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountCredentialService
{
    /**
     * Change a password and revoke every existing login for the account.
     */
    public function resetPassword(User $actor, User $target, string $password): void
    {
        DB::transaction(function () use ($actor, $target, $password) {
            $target->password = $password;
            $target->setRememberToken(Str::random(60));
            $target->save();

            DB::table('sessions')->where('user_id', $target->id)->delete();

            AuditLog::record($actor, null, AuditLog::ACTION_ACCOUNT_PASSWORD_RESET, $target);
        });
    }
}
