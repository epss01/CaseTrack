<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
     * Each of these now carries target_user_id — who the action was taken
     * on, not just who took it. See ACTION_UPDATE for the one site (case
     * reassignment) that still doesn't: out of scope for the audit-events
     * pass that added the column, flagged as a follow-up.
     */
    public const ACTION_REGISTRATION_APPROVED = 'REGISTRATION_APPROVED';

    public const ACTION_REGISTRATION_REJECTED = 'REGISTRATION_REJECTED';

    public const ACTION_ACCOUNT_ACTIVATED = 'ACCOUNT_ACTIVATED';

    public const ACTION_ACCOUNT_DEACTIVATED = 'ACCOUNT_DEACTIVATED';

    public const ACTION_ACCOUNT_ROLE_CHANGED = 'ACCOUNT_ROLE_CHANGED';

    /**
     * A password reset performed outside the ordinary account flows (e.g. an
     * operator running users:rotate-password from the shell) or through the
     * account-management screen. Carries a null case_id, same precedent as
     * the rating-change and export entries, and target_user_id — see the
     * note above ACTION_REGISTRATION_APPROVED.
     */
    public const ACTION_ACCOUNT_PASSWORD_RESET = 'ACCOUNT_PASSWORD_RESET';

    /**
     * A successful authentication — form login or a remember-me cookie
     * resuming a session without one. Fired from a Login event listener
     * (AppServiceProvider::boot()), not a controller hook, specifically so
     * the remember-me path isn't missed. target_user_id is always null: a
     * login acts on no one but the person logging in.
     */
    public const ACTION_LOGIN = 'LOGIN';

    /**
     * A new account coming into existence — public self-registration or
     * `php artisan make:admin`. Distinct from ACTION_REGISTRATION_APPROVED:
     * that's an admin's decision about an account that already exists, this
     * is the account being created in the first place, before any admin has
     * seen it. actor and target are the same user — there is no one else to
     * attribute creation to.
     */
    public const ACTION_ACCOUNT_CREATED = 'ACCOUNT_CREATED';

    /**
     * A policy or middleware denial on an authenticated request — the
     * reconnaissance-by-URL-probing case this constant exists to make
     * visible. case_id is set when the denied route resolved a CaseModel,
     * null otherwise (e.g. a non-Admin hitting /admin/*). target_user_id is
     * always null: a denial has no target, only an actor who was refused.
     */
    public const ACTION_ACCESS_DENIED = 'ACCESS_DENIED';

    /**
     * Every action constant, in one place. RoleActions::ALL precedent — for
     * the audit log viewer's filter dropdown and its Rule::in(), so the
     * viewer's vocabulary can't silently drift from what record() accepts.
     *
     * @var list<string>
     */
    public const ACTIONS = [
        self::ACTION_UPDATE,
        self::ACTION_DELETE,
        self::ACTION_CREATE,
        self::ACTION_EDIT,
        self::ACTION_TIMELINE_UPDATE,
        self::ACTION_CLOSURE_PROPOSED,
        self::ACTION_CLOSURE_CONFIRMED,
        self::ACTION_CLOSURE_REJECTED,
        self::ACTION_EXPORTED,
        self::ACTION_REGISTRATION_APPROVED,
        self::ACTION_REGISTRATION_REJECTED,
        self::ACTION_ACCOUNT_ACTIVATED,
        self::ACTION_ACCOUNT_DEACTIVATED,
        self::ACTION_ACCOUNT_ROLE_CHANGED,
        self::ACTION_ACCOUNT_PASSWORD_RESET,
        self::ACTION_LOGIN,
        self::ACTION_ACCOUNT_CREATED,
        self::ACTION_ACCESS_DENIED,
    ];

    const UPDATED_AT = null;
    const CREATED_AT = 'timestamp';

    protected $fillable = [
        'user_id',
        'target_user_id',
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
     * entry. $target is optional for the same reason on the other side: many
     * actions (a login, an export, a denial) have no one to name but the
     * actor.
     */
    public static function record(User $user, ?CaseModel $case, string $action, ?User $target = null): self
    {
        return static::create([
            'user_id' => $user->id,
            'target_user_id' => $target?->id,
            'case_id' => $case?->id,
            'action_performed' => $action,
        ]);
    }

    /**
     * Narrow the query to the audit log viewer's filters.
     *
     * Mirrors CaseModel::scopeFilteredBy() — one ->when() per filter, so an
     * absent filter is simply skipped rather than needing a branch.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilteredBy(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filters['action'] ?? null, fn (Builder $q, $action) => $q->where('action_performed', $action))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('timestamp', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('timestamp', '<=', $to));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}
