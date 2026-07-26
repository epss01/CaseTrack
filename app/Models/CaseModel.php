<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseModel extends Model
{
    use HasFactory;

    /**
     * Deleting a case keeps the row, so its audit_logs entries keep a valid
     * case_id and a case removed in error can be restored.
     */
    use SoftDeletes;

    /**
     * The status a case carries the moment it is docketed at intake.
     */
    public const STATUS_DOCKETED = 'Docketed';

    protected $table = 'cases';

    protected $fillable = [
        'docket_no',
        'case_title',
        'incident_details',
        'source_info',
        'investigator_id',
        'status',
        'complexity_weight',
    ];

    protected function casts(): array
    {
        return [
            'investigator_id' => 'integer',
            'complexity_weight' => 'integer',
        ];
    }

    /**
     * Limit the query to the cases the given user is allowed to see.
     *
     * Supervisors see the whole office; investigators see their own caseload.
     * This mirrors CaseModelPolicy::view() for list queries.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSupervisor()
            ? $query
            : $query->where('investigator_id', $user->id);
    }

    public function investigator()
    {
        return $this->belongsTo(User::class, 'investigator_id');
    }

    public function timeline()
    {
        return $this->hasOne(CaseTimeline::class, 'case_id');
    }

    public function victims()
    {
        return $this->hasMany(Victim::class, 'case_id');
    }

    public function respondents()
    {
        return $this->hasMany(Respondent::class, 'case_id');
    }

    /**
     * A case may name several complainants, or none at all when it is opened
     * from a media report or on the Commission's own initiative.
     */
    public function complainants()
    {
        return $this->hasMany(Complainant::class, 'case_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'case_id');
    }
}
