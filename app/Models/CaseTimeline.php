<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseTimeline extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'date_of_docket',
        'date_submission_rop',
        'extension_30_days',
        'submission_60th_day',
        'submission_120th_day',
        'target_date_fir',
        'date_fir_submitted',
        'date_submitted_to',
    ];

    protected function casts(): array
    {
        return [
            'date_of_docket' => 'date',
            'date_submission_rop' => 'date',
            'extension_30_days' => 'date',
            'submission_60th_day' => 'date',
            'submission_120th_day' => 'date',
            'target_date_fir' => 'date',
            'date_fir_submitted' => 'date',
        ];
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}
