<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class CaseExtensionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $actor = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($actor)->create(['status' => CaseModel::STATUS_DOCKETED, 'is_torture_case' => true]);
        $case->timeline()->create(['date_of_docket' => '2026-01-01']);

        return [$actor, $case];
    }

    private function event(User $actor, CaseModel $case, string $action, string $note, ?string $previous = null, ?string $deadline = '2026-04-01'): AuditLog
    {
        return AuditLog::record($actor, $case, $action, notes: json_encode([
            'previous_deadline' => $previous, 'deadline' => $deadline, 'note' => $note,
        ], JSON_THROW_ON_ERROR));
    }

    public function test_history_displays_all_extension_events_newest_first_without_auditing_the_view(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00'));
        [$actor, $case] = $this->fixture();
        $this->event($actor, $case, AuditLog::ACTION_TORTURE_EXTENSION_RECORDED, 'Reference recorded');
        $this->event($actor, $case, AuditLog::ACTION_TORTURE_EXTENSION_RECALCULATED, 'Docket correction', '2026-04-01', '2026-04-02');
        $this->event($actor, $case, AuditLog::ACTION_TORTURE_EXTENSION_REMOVED, 'Mistaken grant removed', '2026-04-02', null);
        AuditLog::record($actor, $case, AuditLog::ACTION_EDIT, notes: 'Unrelated edit must stay out');
        $before = AuditLog::count();

        $this->actingAs($actor)->get(route('cases.show', $case))->assertOk()
            ->assertSeeInOrder(['Extension removed', 'Extension recalculated', 'Extension recorded'])
            ->assertSee($actor->full_name)->assertSee('11 Nov 2026 12:00 UTC')
            ->assertSee('2026-04-01')->assertSee('2026-04-02')->assertSee('None recorded')
            ->assertSee('Reference recorded')->assertSee('Docket correction')->assertSee('Mistaken grant removed')
            ->assertDontSee('Unrelated edit must stay out');
        $this->assertSame($before, AuditLog::count());
    }

    public function test_history_is_scoped_to_the_authorized_case_and_remains_visible_to_supervisors(): void
    {
        [$actor, $case] = $this->fixture();
        [$other, $private] = $this->fixture();
        $this->event($actor, $case, AuditLog::ACTION_TORTURE_EXTENSION_RECORDED, 'Own authorized reference');
        $this->event($other, $private, AuditLog::ACTION_TORTURE_EXTENSION_RECORDED, 'Private unrelated reference');
        $this->actingAs($actor)->get(route('cases.show', $case))->assertOk()->assertSee('Own authorized reference')->assertDontSee('Private unrelated reference');
        $this->get(route('cases.show', $private))->assertForbidden()->assertDontSee('Private unrelated reference');
        $this->actingAs(User::factory()->supervisor()->create())->get(route('cases.show', $case))->assertOk()->assertSee('Own authorized reference')->assertDontSee('Private unrelated reference');
        $this->actingAs(User::factory()->admin()->create())->get(route('cases.show', $case))->assertForbidden()->assertDontSee('Own authorized reference');
        $this->app['auth']->forgetGuards();
        $this->get(route('cases.show', $case))->assertRedirect(route('login'));
    }

    public function test_pagination_uses_timestamp_then_id_and_preserves_the_history_anchor(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00'));
        [$actor, $case] = $this->fixture();
        for ($i = 0; $i < 11; $i++) {
            $this->event($actor, $case, AuditLog::ACTION_TORTURE_EXTENSION_RECORDED, 'History reference '.$i);
        }
        $this->event($actor, $case, AuditLog::ACTION_TORTURE_EXTENSION_RECORDED, 'Older timestamp inserted last')
            ->forceFill(['timestamp' => CarbonImmutable::parse('2026-11-10 12:00:00')])->save();
        $first = $this->actingAs($actor)->get(route('cases.show', $case))->assertOk()
            ->assertSee('History reference 10')->assertDontSee('History reference 0')
            ->assertDontSee('Older timestamp inserted last')
            ->assertSee('extension_history_page=2#extension-history', false);
        $this->assertSame(12, $first->viewData('extensionHistory')->total());
        $this->assertCount(10, $first->viewData('extensionHistory'));
        $this->get(route('cases.show', [$case, 'extension_history_page' => 2]))->assertOk()
            ->assertSeeInOrder(['History reference 0', 'Older timestamp inserted last'])->assertDontSee('History reference 10');
        $this->get(route('cases.show', [$case, 'extension_history_page' => 99]))->assertOk()
            ->assertSee('No extension entries on this page.')->assertDontSee('No extension history recorded.');
    }

    public function test_legacy_grants_and_empty_history_do_not_invent_approval_details(): void
    {
        [$actor, $case] = $this->fixture();
        $this->actingAs($actor)->get(route('cases.show', $case))->assertOk()->assertSee('No extension history recorded.');
        $case->timeline->update(['extension_60th_days' => '2026-04-01']);
        $this->get(route('cases.show', $case))->assertOk()
            ->assertSee('An extension deadline is stored, but no extension audit history is available.')
            ->assertSee('The approval reference, recorder and recorded time are unknown.');
    }

    public function test_malformed_historical_notes_do_not_crash_or_display_fabricated_dates(): void
    {
        [$actor, $case] = $this->fixture();
        foreach (['Legacy plain text', '{invalid json', 'null', '[]',
            '{"previous_deadline":null,"deadline":"2026-02-30","note":"Bad date"}',
            '{"previous_deadline":null,"deadline":"2026-04-01","note":[]}',
            '{"deadline":"2026-04-01","note":"Missing previous"}',
        ] as $notes) {
            $audit = AuditLog::record($actor, $case, AuditLog::ACTION_TORTURE_EXTENSION_RECORDED, notes: $notes);
            $this->assertNull($audit->extensionHistoryDetails());
        }
        $this->actingAs($actor)->get(route('cases.show', $case))->assertOk()
            ->assertSee('Unavailable: the historical note does not contain a readable extension record.')
            ->assertDontSee('2026-02-30')->assertDontSee('Missing previous');
    }

    public function test_notes_and_recorder_names_are_escaped_and_inactive_accounts_keep_their_names(): void
    {
        [$actor, $case] = $this->fixture();
        $recorder = User::factory()->investigator()->create(['first_name' => '<img src=x onerror=alert(1)>', 'last_name' => 'Synthetic', 'is_active' => false]);
        $this->event($recorder, $case, AuditLog::ACTION_TORTURE_EXTENSION_RECORDED, '<script>alert("reference")</script>');
        $this->actingAs($actor)->get(route('cases.show', $case))->assertOk()
            ->assertSee($recorder->full_name)->assertSee('<script>alert("reference")</script>')
            ->assertDontSee('<script>alert("reference")</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_missing_recorder_is_explicit_and_empty_reference_is_not_invented(): void
    {
        [$actor, $case] = $this->fixture();
        $audit = $this->event($actor, $case, AuditLog::ACTION_TORTURE_EXTENSION_RECORDED, '');
        $audit->setRelation('user', null);
        $html = view('cases.partials.extension-history', [
            'case' => $case, 'timeline' => $case->timeline,
            'extensionHistory' => new LengthAwarePaginator([$audit], 1, 10),
        ])->render();
        $this->assertStringContainsString('Account unavailable', $html);
        $this->assertStringContainsString('Not recorded', $html);
        $this->assertStringNotContainsString($actor->full_name, $html);
    }
}
