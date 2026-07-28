<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

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

    /**
     * The status that takes a case out of an investigator's active caseload.
     */
    public const STATUS_CLOSED = 'Closed';

    /**
     * A closure the assigned investigator has asked for but no supervisor has
     * decided on yet.
     *
     * Deliberately not excluded by scopeActive(): the case is not closed until
     * the supervisor says so, so it keeps counting toward its investigator's
     * Workload Capacity Score for as long as it sits here.
     */
    public const STATUS_PENDING_CLOSURE = 'Pending Closure';

    protected $table = 'cases';

    protected $fillable = [
        'docket_no',
        'case_title',
        'incident_details',
        'source_info',
        'investigator_id',
        'status',
        'status_before_closure',
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
     * Validation rules for the complexity weight (C_j in the Workload
     * Capacity Score).
     *
     * A 1-5 judgment made when the case is docketed. Shared by intake and the
     * edit form so the two cannot drift apart.
     *
     * @return list<string>
     */
    public static function complexityWeightRules(): array
    {
        return ['required', 'integer', 'between:1,5'];
    }

    /**
     * Validation rules for the status field on the ordinary edit form.
     *
     * Status is otherwise free text, but the two closure states are not the
     * edit form's to hand out: closing a case runs through the maker-checker
     * workflow, and letting anyone type "Closed" into a text box would make
     * that workflow decorative.
     *
     * The rules depend on the case because a case already sitting in one of
     * those states still has to be editable — resubmitting its own status must
     * pass, or its title could never be corrected again.
     *
     * @return list<string|\Illuminate\Validation\Rules\In|\Illuminate\Validation\Rules\NotIn>
     */
    public static function statusRulesFor(self $case): array
    {
        // Awaiting a supervisor's decision: the status is the workflow's to
        // move, not the form's. Anything else would be an unaudited way to
        // withdraw a proposal.
        if ($case->status === self::STATUS_PENDING_CLOSURE) {
            return ['required', Rule::in([self::STATUS_PENDING_CLOSURE])];
        }

        return ['required', 'string', 'max:255', Rule::notIn(
            array_diff([self::STATUS_PENDING_CLOSURE, self::STATUS_CLOSED], [$case->status])
        )];
    }

    /**
     * Limit the query to cases still being worked on.
     *
     * This is A_i in the Workload Capacity Score: a closed case no longer
     * counts against the investigator holding it. Soft-deleted cases are
     * already excluded by SoftDeletes.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
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
