<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'first_name',
        'last_name',
        'office_region',
        'is_staff',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * The user's display name, used wherever the scaffolding showed "name".
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * Determine whether the user holds any of the given roles.
     */
    public function hasRole(string ...$roleNames): bool
    {
        return in_array($this->role?->role_name, $roleNames, true);
    }

    public function isSupervisor(): bool
    {
        return $this->hasRole(Role::SUPERVISOR);
    }

    public function isInvestigator(): bool
    {
        return $this->hasRole(Role::INVESTIGATOR);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function cases()
    {
        return $this->hasMany(CaseModel::class, 'investigator_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
