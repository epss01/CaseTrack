<?php

namespace Tests\Feature;

use App\Services\CaseDeadlineService;
use Tests\TestCase;

class DeadlineBadgeTest extends TestCase
{
    public function test_deadline_states_have_distinct_symbols_and_visible_labels(): void
    {
        $symbols = [];
        foreach ([
            CaseDeadlineService::STATUS_ON_TRACK => 'On track',
            CaseDeadlineService::STATUS_DUE_SOON => 'Due soon',
            CaseDeadlineService::STATUS_OVERDUE => 'Overdue',
            CaseDeadlineService::STATUS_SUBMITTED => 'Submitted',
        ] as $status => $label) {
            $html = view('partials.deadline-badge', ['status' => $status])->render();
            $this->assertStringContainsString($label, $html);
            $this->assertStringContainsString('aria-hidden="true"', $html);
            preg_match('/<svg.*?<\/svg>/s', $html, $symbol);
            $this->assertNotEmpty($symbol);
            $symbols[] = $symbol[0];
        }

        $this->assertCount(4, array_unique($symbols));
    }
}
