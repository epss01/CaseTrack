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
     * Every role that may handle cases.
     *
     * @var list<string>
     */
    public const CASE_HANDLING = [self::INVESTIGATOR, self::SUPERVISOR];

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

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
