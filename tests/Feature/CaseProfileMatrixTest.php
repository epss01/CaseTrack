<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseProfileMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_case_details_parties_and_timeline_in_one_page(): void
    {
        $investigator = User::factory()->investigator()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'office_region' => 'CHR Region VIII',
        ]);

        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'docket_no' => 'CHR-VIII-2026-0200',
            'case_title' => 'Alleged unlawful arrest in Tacloban',
            'incident_details' => 'Complainant reports a warrantless arrest.',
            'source_info' => 'Walk-in',
            'status' => 'Under investigation',
        ]);

        $case->victims()->create(['name' => 'Maria Santos', 'age' => 34, 'status' => 'Injured', 'sector' => 'Farmer']);
        $case->respondents()->create(['name' => 'PO1 Cruz', 'age' => 29, 'status' => 'Active duty', 'sector' => 'PNP']);

        $case->timeline()->create([
            'date_of_docket' => '2026-06-12',
            'submission_60th_day' => '2026-08-11',
            'date_submitted_to' => 'Regional Director',
        ]);

        $this->actingAs($investigator)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee('Case Profile Matrix')
            // Case details
            ->assertSee('CHR-VIII-2026-0200')
            ->assertSee('Alleged unlawful arrest in Tacloban')
            ->assertSee('Under investigation')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('CHR Region VIII')
            ->assertSee('Walk-in')
            ->assertSee('Complainant reports a warrantless arrest.')
            // Victim profile
            ->assertSee('Maria Santos')
            ->assertSee('Injured')
            ->assertSee('Farmer')
            // Respondent profile
            ->assertSee('PO1 Cruz')
            ->assertSee('Active duty')
            ->assertSee('PNP')
            // Timeline
            ->assertSee('12 Jun 2026')
            ->assertSee('11 Aug 2026')
            ->assertSee('Regional Director');
    }

    public function test_it_renders_when_a_case_has_no_timeline_yet(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->victims()->create(['name' => 'Maria Santos', 'status' => 'Injured', 'sector' => 'Farmer']);

        $this->actingAs($investigator)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee('No timeline has been recorded for this case yet.')
            ->assertSee('Date of Docket')
            ->assertSee('No respondents recorded.');
    }

    public function test_a_case_is_visible_on_its_profile_matrix_immediately_after_intake(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)->post(route('cases.store'), [
            'docket_no' => 'CHR-VIII-2026-0300',
            'case_title' => 'Complaint re: detention conditions',
            'incident_details' => 'Reported overcrowding.',
            'source_info' => 'Referral',
            'victims' => [['name' => 'Ana Lim', 'age' => '41', 'status' => 'Detained', 'sector' => 'Urban poor']],
            'respondents' => [['name' => 'Jail Warden', 'age' => '', 'status' => '', 'sector' => '']],
        ]);

        $case = CaseModel::where('docket_no', 'CHR-VIII-2026-0300')->firstOrFail();

        $this->actingAs($investigator)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee('Case docketed.')
            ->assertSee('Ana Lim')
            ->assertSee('Urban poor')
            ->assertSee('Jail Warden');
    }

    public function test_the_profile_matrix_is_still_closed_to_other_investigators(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($colleague)->create();

        $this->actingAs($investigator)
            ->get("/cases/{$case->id}")
            ->assertForbidden();
    }
}
