<?php

namespace App\Console\Commands;

use App\Models\CaseTimeline;
use App\Services\CaseDeadlineService;
use Illuminate\Console\Command;

class CheckTortureDeadlines extends Command
{
    protected $signature = 'cases:check-torture-deadlines';

    protected $description = 'Read-only preflight for stored torture-case deadlines against the prototype 60/90-day rule';

    public function handle(): int
    {
        $baseConflicts = 0;
        $extensionConflicts = 0;
        $classificationConflicts = 0;
        CaseTimeline::query()->whereHas('case')->with('case:id,is_torture_case')
            ->chunkById(200, function ($timelines) use (&$baseConflicts, &$extensionConflicts, &$classificationConflicts) {
                foreach ($timelines as $timeline) {
                    if ($timeline->case?->is_torture_case === true && $timeline->submission_60th_day !== null
                        && ($timeline->date_of_docket === null || ! $timeline->submission_60th_day->isSameDay(CaseDeadlineService::deadlineOn($timeline->date_of_docket, CaseDeadlineService::SIXTIETH_DAY)))) {
                        $baseConflicts++;
                    }
                    if ($timeline->extension_60th_days !== null) {
                        if ($timeline->case?->is_torture_case !== true) {
                            $classificationConflicts++;
                        }
                        if ($timeline->date_of_docket === null || ! $timeline->extension_60th_days->isSameDay(CaseDeadlineService::deadlineOn($timeline->date_of_docket, CaseDeadlineService::EXTENDED_TORTURE_DAYS))) {
                            $extensionConflicts++;
                        }
                    }
                }
            });

        $this->line('Base deadline conflicts: '.$baseConflicts);
        $this->line('Extended deadline conflicts: '.$extensionConflicts);
        $this->line('Extension classification conflicts: '.$classificationConflicts);
        if ($baseConflicts + $extensionConflicts + $classificationConflicts > 0) {
            $this->warn('Resolve conflicts through authorized record corrections before office rollout. No records were changed.');

            return self::FAILURE;
        }
        $this->info('No stored-date conflicts found. No records were changed.');

        return self::SUCCESS;
    }
}
