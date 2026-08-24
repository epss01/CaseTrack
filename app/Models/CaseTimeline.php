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

    /**
     * Validation rules keeping every milestone on or after the date the case
     * was docketed.
     *
     * Nothing has enforced this before — the manual Set Timeline form
     * (UpdateCaseTimelineRequest) had no after:/before: rules, so a 120th day
     * could be typed earlier than the date of docket by hand. Shared here
     * rather than duplicated so both the manual form and case import gain
     * the same guard from one definition.
     *
     * Deliberately anchors every milestone to date_of_docket only, not to
     * each other (e.g. ROP submitted before the 120th day) — the naming of
     * extension_30_days is itself ambiguous between "an extension granted"
     * and "the 30-day deadline" (see wiki/project/database-schema.md), and
     * asserting an order between milestones risks rejecting genuinely valid
     * data over that ambiguity. date_of_docket is the one column every case
     * is guaranteed to have and the one anchor every milestone is safe to
     * require chronologically.
     *
     * These are plain validation rule strings, not offset or difference
     * arithmetic, so this stays outside the date math CLAUDE.md reserves for
     * CaseDeadlineService.
     *
     * @return array<string, list<string>>
     */
    public static function chronologyRules(): array
    {
        $after = ['after_or_equal:date_of_docket'];

        return [
            'date_submission_rop' => $after,
            'extension_30_days' => $after,
            'submission_60th_day' => $after,
            'submission_120th_day' => $after,
            'target_date_fir' => $after,
            'date_fir_submitted' => $after,
        ];
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}
