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
     */
    public static function record(User $user, CaseModel $case, string $action): self
    {
        return static::create([
            'user_id' => $user->id,
            'case_id' => $case->id,
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
