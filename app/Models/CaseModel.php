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

    /**
     * A case actively being worked, prior to review.
     */
    public const STATUS_UNDER_INVESTIGATION = 'Under investigation';

    /**
     * A case awaiting a supervisor's review before it can move further.
     */
    public const STATUS_FOR_REVIEW = 'For review';

    /**
     * The ratified five-value status vocabulary (CHR-Answers-2026-08-01),
     * each mapped to the colour it renders as everywhere a status is shown.
     *
     * Listed in the order a case moves through them — this array also drives
     * legend/sort order wherever it's consumed. A status not listed here
     * (status is still an unconstrained string column) is still handled by
     * every consumer, via a hashed fallback; it just isn't one of the five.
     */
    public const STATUS_COLOURS = [
        self::STATUS_DOCKETED => '#1d4ed8',
        self::STATUS_UNDER_INVESTIGATION => '#3b82f6',
        self::STATUS_FOR_REVIEW => '#93c5fd',
        self::STATUS_PENDING_CLOSURE => '#b45309',
        self::STATUS_CLOSED => '#94a3b8',
    ];

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
        'is_torture_case',
    ];

    protected function casts(): array
    {
        return [
            'investigator_id' => 'integer',
            'complexity_weight' => 'integer',
            'is_torture_case' => 'boolean',
        ];
    }

    /**
     * "New" if docketed this calendar year, "Pending" if an earlier year
     * (CHR-Answers-2026-08-01, item 8). Derived, not stored, so it can never
     * drift out of sync with date_of_docket. Null when the case has no
     * timeline row yet — load the timeline relation before reading this or
     * it N+1s per case.
     */
    protected function docketPhase(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function () {
                $docketedOn = $this->timeline?->date_of_docket;

                if ($docketedOn === null) {
                    return null;
                }

                return $docketedOn->year === now()->year ? 'New' : 'Pending';
            },
        );
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

    /**
     * Narrow the query to the report filters the user asked for.
     *
     * A scope rather than controller code so every report — the listing, the
     * CSV, the per-case page — filters through one definition, the way
     * scopeVisibleTo() gives them all one answer to who may see what. An absent
     * or empty filter drops out via when(), so no filter means the whole
     * visible set.
     *
     * The date range reads case_timelines.date_of_docket, which is the only
     * date a case is guaranteed to have. That has a consequence worth stating:
     * a case with no timeline row cannot satisfy a date filter and disappears
     * from the results, so a filtered report must report that count separately
     * rather than let the cases vanish silently — the same call /alerts makes
     * with its untracked tile.
     *
     * The dates arrive as validated strings and are handed to the query as
     * they are. Parsing them here would be date math on a case_timelines
     * column outside CaseDeadlineService (CLAUDE.md, Conventions).
     *
     * whereDate() rather than a plain comparison, and it is not cosmetic: the
     * date cast on CaseTimeline stores date_of_docket as '2026-03-31 00:00:00',
     * which string-compares greater than '2026-03-31' and drops the last day of
     * every range. MySQL's real DATE column hides that; the SQLite the tests
     * run on does not. whereDate() normalizes both sides on either driver.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilteredBy(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['investigator_id'] ?? null, fn (Builder $q, $id) => $q->where('investigator_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereHas(
                'timeline', fn (Builder $timeline) => $timeline->whereDate('date_of_docket', '>=', $from)
            ))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereHas(
                'timeline', fn (Builder $timeline) => $timeline->whereDate('date_of_docket', '<=', $to)
            ));
    }

    /**
     * Narrow the query to cases matching a free-text search term.
     *
     * Matches the case's own docket number and title, or the name of any
     * linked victim, respondent, or complainant — there is no standalone
     * people-directory page, so "search the case and people tables" resolves
     * to this one scope reached through from /cases.
     *
     * The whole disjunction sits inside one where(fn ...) closure — that
     * grouping is the security boundary. scopeVisibleTo() adds a leading
     * `where investigator_id = ?`; an ungrouped orWhere here would turn "my
     * cases matching X" into "my cases, or anyone's case matching X". Chain
     * search() after visibleTo(), never before.
     *
     * ponytail: % and _ in $term act as LIKE wildcards, which only widens the
     * match set within the caller's already-scoped query — it cannot escape
     * scopeVisibleTo(). Not escaped: SQLite needs an explicit ESCAPE clause
     * to neutralize them and MySQL doesn't, and the behaviour this guards
     * against (a slightly looser match) isn't worth a driver-specific branch.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('docket_no', 'like', $like)
                ->orWhere('case_title', 'like', $like);

            foreach (['victims', 'respondents', 'complainants'] as $relation) {
                $q->orWhereHas($relation, fn (Builder $people) => $people->where('name', 'like', $like));
            }
        });
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
