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
     * Registration status. Free text with named constants, matching how
     * CaseModel::status works — pending is the fail-closed default set by
     * the column itself, so a row created any other way than through
     * RegisterController or the Admin account-management screen still
     * cannot log in.
     */
    public const REGISTRATION_PENDING = 'pending';

    public const REGISTRATION_APPROVED = 'approved';

    public const REGISTRATION_REJECTED = 'rejected';

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
        'registration_status',
        'approved_by',
        'approved_at',
        'is_active',
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
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
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
     * Reuses WorkloadController's withSum() alias when the caller loaded one
     * — array_key_exists, not ??, because the alias is legitimately NULL for
     * an investigator with no active cases, and ?? would fall through to the
     * per-user query for exactly the users who need it least. Falls back to
     * its own query when no alias was loaded (e.g. a bare factory instance in
     * a test), so this keeps working without the caller's cooperation.
     */
    public function workloadCapacityScore(): float
    {
        if ($this->workloadCapacityScore !== null) {
            return $this->workloadCapacityScore;
        }

        $activeComplexitySum = array_key_exists('active_complexity_sum', $this->attributes)
            ? (float) ($this->attributes['active_complexity_sum'] ?? 0)
            : $this->cases()->active()->sum('complexity_weight');

        return $this->workloadCapacityScore = $activeComplexitySum * (2 - $this->performance_rating);
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

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The admin who approved this registration, if any.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
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
