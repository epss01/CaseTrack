<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class Role extends Model
{
    use HasFactory;

    public const INVESTIGATOR = 'Investigator';

    public const SUPERVISOR = 'Supervisor';

    /**
     * Identity/access administration — registration approval and account
     * management. Deliberately outside CASE_HANDLING: an Admin never opens,
     * views, or edits a case, so CaseModelPolicy and the case-handling nav
     * links must not treat this role as one of the two that do.
     */
    public const ADMIN = 'Admin';

    /**
     * Every role that may handle cases.
     *
     * @var list<string>
     */
    public const CASE_HANDLING = [self::INVESTIGATOR, self::SUPERVISOR];

    /**
     * Every role in the system. For RoleSeeder only — CASE_HANDLING stays the
     * one policies and nav links key off, so adding Admin here can't widen
     * either by accident.
     *
     * @var list<string>
     */
    public const ALL = [self::INVESTIGATOR, self::SUPERVISOR, self::ADMIN];

    protected $fillable = [
        'role_name',
    ];

    /**
     * The role assigned to newly registered users.
     */
    public static function default(): self
    {
        return static::firstOrCreate(['role_name' => self::INVESTIGATOR]);
    }

    /**
     * Validation rule constraining a user id to someone who actually holds
     * the Investigator role — a case may not be assigned to a supervisor.
     *
     * Shared by intake (StoreCaseRequest) and reassignment
     * (ReassignCaseRequest) so the two cannot drift apart.
     */
    public static function assignableInvestigatorRule(): Exists
    {
        return Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn(
            'role_id',
            static::query()->where('role_name', self::INVESTIGATOR)->select('id')
        ));
    }

    /**
     * Validation rule constraining a role id to Investigator or Supervisor.
     *
     * The security boundary between "case-handling role a person may pick or
     * be given" and "Admin, provisioned separately": shared by the
     * registration form (a registrant selects their own role) and the
     * account-management role-change screen (an admin changes someone
     * else's), so neither can be POSTed the Admin role's id.
     */
    public static function selectableRoleRule(): Exists
    {
        return Rule::exists('roles', 'id')->whereIn('role_name', self::CASE_HANDLING);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
