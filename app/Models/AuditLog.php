<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * Actions worth tracing back to the user who performed them.
     */
    public const ACTION_UPDATE = 'UPDATE';

    public const ACTION_DELETE = 'DELETE';

    /**
     * A case docketed at intake — the origin of every case record.
     */
    public const ACTION_CREATE = 'CREATE';

    /**
     * A field edit through the ordinary edit form.
     *
     * Kept apart from ACTION_UPDATE, which predates it and still marks a
     * reassignment (and a performance-rating change, with a null case_id) — a
     * trail that cannot tell "the title was corrected" from "the case changed
     * hands" cannot say who moved a case.
     */
    public const ACTION_EDIT = 'EDIT';

    /**
     * A statutory milestone date set or moved via Set Timeline.
     *
     * Distinct from ACTION_EDIT: the 30/60/120-day deadlines are computed off
     * these columns, so moving one moves what the system reports as overdue.
     */
    public const ACTION_TIMELINE_UPDATE = 'TIMELINE_UPDATE';

    /**
     * The two halves of maker-checker on case closure, kept apart on purpose:
     * a trail that cannot tell a request from an approval cannot answer who
     * asked for a case to be closed, only that it was.
     */
    public const ACTION_CLOSURE_PROPOSED = 'CLOSURE_PROPOSED';

    public const ACTION_CLOSURE_CONFIRMED = 'CLOSURE_CONFIRMED';

    public const ACTION_CLOSURE_REJECTED = 'CLOSURE_REJECTED';

    /**
     * A case listing downloaded as a file.
     *
     * The one entry here that records a read rather than a change. Case data
     * leaving the system in a file is the access question worth being able to
     * answer later; viewing the same listing in a page is not, or the trail
     * would fill with page views and stop being readable. Carries a null
     * case_id — an export spans a filtered set, not one case.
     */
    public const ACTION_EXPORTED = 'EXPORTED';

    /**
     * Admin actions on registrations and accounts. All carry a null case_id,
     * same precedent as the rating-change and export entries — none of them
     * are scoped to a case.
     *
     * Known limitation, inherited rather than introduced here: audit_logs
     * has no target-user column, so these name the admin who acted, not
     * who was acted on. Same open gap already logged against ACTION_UPDATE
     * for rating changes. Distinct activated/deactivated constants at least
     * keep the direction of the change recoverable from the constant alone.
     */
    public const ACTION_REGISTRATION_APPROVED = 'REGISTRATION_APPROVED';

    public const ACTION_REGISTRATION_REJECTED = 'REGISTRATION_REJECTED';

    public const ACTION_ACCOUNT_ACTIVATED = 'ACCOUNT_ACTIVATED';

    public const ACTION_ACCOUNT_DEACTIVATED = 'ACCOUNT_DEACTIVATED';

    public const ACTION_ACCOUNT_ROLE_CHANGED = 'ACCOUNT_ROLE_CHANGED';

    public const ACTION_ACCOUNT_PASSWORD_RESET = 'ACCOUNT_PASSWORD_RESET';

    const UPDATED_AT = null;
    const CREATED_AT = 'timestamp';

    protected $fillable = [
        'user_id',
        'case_id',
        'action_performed',
    ];

    /**
     * Write an entry to the audit trail.
     *
     * The timestamp fills itself: this table has no created_at/updated_at
     * pair, only the `timestamp` column mapped above.
     *
     * $case is optional because not every action worth tracing belongs to a
     * case — changing an investigator's performance rating changes who gets
     * assigned casework, and case_id is nullable for exactly this shape of
     * entry.
     */
    public static function record(User $user, ?CaseModel $case, string $action): self
    {
        return static::create([
            'user_id' => $user->id,
            'case_id' => $case?->id,
            'action_performed' => $action,
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}
