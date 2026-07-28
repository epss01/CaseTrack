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
        'performance_rating',
        'role_id',
    ];

    /**
     * Cached Workload Capacity Score, so sorting a picker by it does not
     * re-run the aggregate once per comparison.
     */
    private ?float $workloadCapacityScore = null;

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
            'performance_rating' => 'float',
            'password' => 'hashed',
        ];
    }

    /**
     * The investigator's Workload Capacity Score.
     *
     *     WCS_i = sum over active cases j of C_j x (2 - P_i)
     *
     * C_j is the case's complexity weight, P_i this user's performance rating.
     * Since P_i is constant across the sum it factors out, so this is the sum
     * of the active caseload's weights scaled by (2 - P_i): a lower-rated
     * investigator is treated as more loaded by the same set of cases.
     *
     * Lower relative scores are the ones suggested for a new assignment.
     * Source: raw/wcs/Workload-Capacity-Score.docx in the project vault.
     *
     * ponytail: one aggregate query per investigator, so a picker of five
     * costs five SUMs. Move to withSum() on the picker query if the office
     * ever holds more than a couple of dozen investigators.
     */
    public function workloadCapacityScore(): float
    {
        return $this->workloadCapacityScore ??=
            $this->cases()->active()->sum('complexity_weight') * (2 - $this->performance_rating);
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
