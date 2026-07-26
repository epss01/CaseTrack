<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
