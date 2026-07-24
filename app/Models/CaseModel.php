<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseModel extends Model
{
    use HasFactory;

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

    public function complainant()
    {
        return $this->hasOne(Complainant::class, 'case_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'case_id');
    }
}
