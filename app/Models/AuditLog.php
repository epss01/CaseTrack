<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;
    const CREATED_AT = 'timestamp';

    protected $fillable = [
        'user_id',
        'case_id',
        'action_performed',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}
