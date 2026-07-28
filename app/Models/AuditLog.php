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
     * The two halves of maker-checker on case closure, kept apart on purpose:
     * a trail that cannot tell a request from an approval cannot answer who
     * asked for a case to be closed, only that it was.
     */
    public const ACTION_CLOSURE_PROPOSED = 'CLOSURE_PROPOSED';

    public const ACTION_CLOSURE_CONFIRMED = 'CLOSURE_CONFIRMED';

    public const ACTION_CLOSURE_REJECTED = 'CLOSURE_REJECTED';

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
