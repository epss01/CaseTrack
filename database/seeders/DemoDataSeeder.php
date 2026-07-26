<?php

namespace Database\Seeders;

use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\Complainant;
use App\Models\Respondent;
use App\Models\Role;
use App\Models\User;
use App\Models\Victim;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Click-through demo data: 2 supervisors, 5 investigators, 15 cases.
 *
 * Deliberately NOT wired into DatabaseSeeder — run it on purpose:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * Every case it creates carries a DEMO- docket number so seeded records can
 * never be mistaken for real CHR casework, and so it cannot collide with
 * anything docketed by hand.
 *
 * Re-running is a no-op once demo cases exist. To rebuild them — and only
 * them, leaving hand-entered cases alone:
 *
 *     php artisan tinker --execute="App\Models\CaseModel::withTrashed()
 *         ->where('docket_no', 'like', 'DEMO-VIII-%')->forceDelete();"
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * Timeline spread
 * ---------------
 * The 30/60/120-day columns are deadlines derived from the date of docket
 * (see CaseTimelineFactory); date_submission_rop and date_fir_submitted are
 * the actuals. Docket dates are set relative to today, so the buckets stay
 * meaningful whenever the seeder is run: 4 overdue, 4 due soon, 4 on track,
 * 3 closed. See bucketFor() for how a case is classified.
 *
 * A deadline only counts against a case while the matching submission is
 * outstanding — a case whose ROP is already filed is not overdue just because
 * its 30-day mark has passed.
 *
 * The 60th-day column is deliberately NOT part of the classification. Per the
 * wiki (project/database-schema, "Open gaps"), that milestone applies only to
 * torture cases, and nothing in the schema records case type — so there is no
 * way to tell which cases it binds. DEMO-VIII-*-0009 is seeded with its 60th
 * day close at hand so the rule can be exercised once a case-type field
 * exists, without affecting the buckets above.
 */
class DemoDataSeeder extends Seeder
{
    private const DOCKET_PREFIX = 'DEMO-VIII-';

    /**
     * The office the demo data belongs to.
     *
     * @var list<array{username: string, first: string, last: string}>
     */
    private const SUPERVISORS = [
        ['username' => 'talonzo', 'first' => 'Teresita', 'last' => 'Alonzo'],
        ['username' => 'epadilla', 'first' => 'Ernesto', 'last' => 'Padilla'],
    ];

    /**
     * @var list<array{username: string, first: string, last: string}>
     */
    private const INVESTIGATORS = [
        ['username' => 'abautista', 'first' => 'Ana Marie', 'last' => 'Bautista'],
        ['username' => 'rtan', 'first' => 'Rogelio', 'last' => 'Tan'],
        ['username' => 'clim', 'first' => 'Cristina', 'last' => 'Lim'],
        ['username' => 'focampo', 'first' => 'Ferdinand', 'last' => 'Ocampo'],
        ['username' => 'mserrano', 'first' => 'Maribel', 'last' => 'Serrano'],
    ];

    /**
     * The 15 cases, written out rather than randomised so the alert buckets
     * are deterministic and reviewable.
     *
     * docketed    - days before today the case was docketed
     * rop / fir   - days after docketing the submission actually happened,
     *               or null if it never did
     * caseload    - index into INVESTIGATORS; the spread is deliberately
     *               uneven (5/4/3/2/1) so workload balancing has something
     *               to show later
     *
     * @var list<array<string, mixed>>
     */
    private const CASES = [
        // ---------------------------------------------------------- OVERDUE
        [
            'bucket' => 'OVERDUE', 'caseload' => 0, 'docketed' => 210,
            'title' => 'Alleged unlawful arrest of farmers in Burauen',
            'source' => 'Walk-in', 'status' => 'Under investigation', 'weight' => 4,
            'rop' => 12, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 2, 'respondents' => 1,
        ],
        [
            'bucket' => 'OVERDUE', 'caseload' => 0, 'docketed' => 165,
            'title' => 'Reported maltreatment of detainees, Ormoc City Jail',
            'source' => 'Referral', 'status' => 'Under investigation', 'weight' => 5,
            'rop' => 20, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 3, 'respondents' => 2,
        ],
        [
            'bucket' => 'OVERDUE', 'caseload' => 1, 'docketed' => 140,
            'title' => 'Complaint on demolition without notice, Tacloban City',
            'source' => 'Walk-in', 'status' => 'For review', 'weight' => 3,
            'rop' => 9, 'fir' => null, 'recipient' => null,
            'complainants' => 2, 'victims' => 2, 'respondents' => 1,
        ],
        [
            // Nothing submitted at all: overdue on every deadline at once.
            'bucket' => 'OVERDUE', 'caseload' => 2, 'docketed' => 128,
            'title' => 'Alleged harassment of fisherfolk, Guiuan',
            'source' => 'Media report', 'status' => 'Under investigation', 'weight' => 2,
            'rop' => null, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 1, 'respondents' => 1,
        ],

        // --------------------------------------------------------- DUE SOON
        [
            'bucket' => 'DUE SOON', 'caseload' => 0, 'docketed' => 118,
            'title' => 'Alleged excessive force during checkpoint, Palo',
            'source' => 'Referral', 'status' => 'For review', 'weight' => 3,
            'rop' => 10, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 1, 'respondents' => 2,
        ],
        [
            // No complainant: opened on the Commission's own initiative.
            'bucket' => 'DUE SOON', 'caseload' => 1, 'docketed' => 113,
            'title' => 'Reported enforced disappearance, Catbalogan',
            'source' => 'Media report', 'status' => 'Under investigation', 'weight' => 5,
            'rop' => 15, 'fir' => null, 'recipient' => null,
            'complainants' => 0, 'victims' => 1, 'respondents' => 2,
        ],
        [
            'bucket' => 'DUE SOON', 'caseload' => 2, 'docketed' => 110,
            'title' => 'Complaint on custodial abuse, Calbayog',
            'source' => 'Walk-in', 'status' => 'Under investigation', 'weight' => 4,
            'rop' => 12, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 2, 'respondents' => 1,
        ],
        [
            // Due soon on the ROP rather than the FIR: the 30-day mark is
            // days away and nothing has been filed against it yet.
            'bucket' => 'DUE SOON', 'caseload' => 3, 'docketed' => 24,
            'title' => 'Alleged illegal eviction of IP families, Samar',
            'source' => 'Endorsement', 'status' => 'Docketed', 'weight' => 3,
            'rop' => null, 'fir' => null, 'recipient' => null,
            'complainants' => 2, 'victims' => 3, 'respondents' => 1,
        ],

        // --------------------------------------------------------- ON TRACK
        [
            // On track on the binding deadlines, but its 60th day is within
            // the fortnight — the case to test the torture-case RORP rule
            // against once a case-type field exists.
            'bucket' => 'ON TRACK', 'caseload' => 0, 'docketed' => 52,
            'title' => 'Reported red-tagging of student leaders, Tacloban City',
            'source' => 'Referral', 'status' => 'Under investigation', 'weight' => 2,
            'rop' => 14, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 2, 'respondents' => 1,
        ],
        [
            'bucket' => 'ON TRACK', 'caseload' => 1, 'docketed' => 14,
            'title' => 'Complaint on denial of medical attention in detention, Baybay',
            'source' => 'Walk-in', 'status' => 'Under investigation', 'weight' => 3,
            'rop' => 9, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 1, 'respondents' => 1,
        ],
        [
            'bucket' => 'ON TRACK', 'caseload' => 2, 'docketed' => 6,
            'title' => 'Alleged verbal abuse by barangay officials, Basey',
            'source' => 'Walk-in', 'status' => 'Docketed', 'weight' => 1,
            'rop' => null, 'fir' => null, 'recipient' => null,
            'complainants' => 0, 'victims' => 1, 'respondents' => 1,
        ],
        [
            'bucket' => 'ON TRACK', 'caseload' => 3, 'docketed' => 2,
            'title' => 'Reported labour rights violation, Isabel',
            'source' => 'Referral', 'status' => 'Docketed', 'weight' => 2,
            'rop' => null, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 2, 'respondents' => 1,
        ],

        // ----------------------------------------------------------- CLOSED
        [
            // Torture case: the one where the 60th-day RORP milestone
            // actually applies. Nothing in the schema records that, which is
            // a gap the alert logic will run into (see wiki database-schema).
            'bucket' => 'CLOSED', 'caseload' => 0, 'docketed' => 150,
            'title' => 'Alleged torture of suspect in custody, Borongan',
            'source' => 'Referral', 'status' => 'Closed', 'weight' => 5,
            'rop' => 11, 'fir' => 95, 'recipient' => 'Regional Director',
            'complainants' => 1, 'victims' => 1, 'respondents' => 2,
        ],
        [
            // Filed after the office's own FIR target but inside the 120-day
            // deadline: behind target, not late.
            'bucket' => 'CLOSED', 'caseload' => 1, 'docketed' => 132,
            'title' => 'Complaint on unlawful search of residence, Maasin',
            'source' => 'Walk-in', 'status' => 'Closed', 'weight' => 3,
            'rop' => 7, 'fir' => 118, 'recipient' => 'Commission en Banc',
            'complainants' => 1, 'victims' => 2, 'respondents' => 1,
        ],
        [
            'bucket' => 'CLOSED', 'caseload' => 4, 'docketed' => 95,
            'title' => 'Reported child rights violation, Naval',
            'source' => 'Endorsement', 'status' => 'Closed', 'weight' => 2,
            'rop' => 6, 'fir' => 88, 'recipient' => 'Regional Director',
            'complainants' => 2, 'victims' => 1, 'respondents' => 1,
        ],
    ];

    /**
     * Classify a timeline into one of the four demo buckets.
     *
     * This is a placeholder for the real alert logic, not the alert logic
     * itself — it exists so the seeder, its summary table and its tests all
     * agree on what "overdue" means.
     *
     * A deadline is only counted while its submission is outstanding: the
     * 30-day mark binds until the ROP is filed, the 120-day mark until the
     * FIR is. The 60th day is excluded — see the class docblock.
     */
    public static function bucketFor(CaseTimeline $timeline, ?CarbonImmutable $today = null): string
    {
        if ($timeline->date_fir_submitted !== null) {
            return 'CLOSED';
        }

        $today ??= CarbonImmutable::today();

        $outstanding = collect([
            $timeline->date_submission_rop === null ? $timeline->extension_30_days : null,
            $timeline->submission_120th_day,
        ])->filter()->map(fn ($deadline) => CarbonImmutable::parse($deadline));

        if ($outstanding->contains(fn (CarbonImmutable $d) => $d->isBefore($today))) {
            return 'OVERDUE';
        }

        if ($outstanding->contains(fn (CarbonImmutable $d) => $d->lessThanOrEqualTo($today->addDays(14)))) {
            return 'DUE SOON';
        }

        return 'ON TRACK';
    }

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $supervisors = $this->staff(self::SUPERVISORS, Role::SUPERVISOR);
        $investigators = $this->staff(self::INVESTIGATORS, Role::INVESTIGATOR);

        $this->say('Demo staff ready: '.count($supervisors).' supervisors, '
            .count($investigators).' investigators (password: "password").');

        if ($this->alreadySeeded()) {
            $this->say('Demo cases already present — skipping. Delete the DEMO- '
                .'cases first if you want them rebuilt.', 'warn');

            return;
        }

        $today = CarbonImmutable::today();

        foreach (self::CASES as $index => $blueprint) {
            $this->createCase($blueprint, $index + 1, $today, $investigators);
        }

        $this->say('Seeded '.count(self::CASES).' demo cases.');
        $this->summarise();
    }

    /**
     * Create the demo staff, reusing any account that already exists.
     *
     * @param  list<array{username: string, first: string, last: string}>  $people
     * @return list<User>
     */
    private function staff(array $people, string $roleName): array
    {
        return array_map(function (array $person) use ($roleName) {
            $existing = User::where('username', $person['username'])->first();

            if ($existing) {
                return $existing;
            }

            return User::factory()->withRole($roleName)->create([
                'username' => $person['username'],
                'first_name' => $person['first'],
                'last_name' => $person['last'],
                'is_staff' => $roleName === Role::SUPERVISOR,
            ]);
        }, $people);
    }

    /**
     * Has a previous run already put demo cases in this database?
     */
    private function alreadySeeded(): bool
    {
        return CaseModel::withTrashed()
            ->where('docket_no', 'like', self::DOCKET_PREFIX.'%')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  list<User>  $investigators
     */
    private function createCase(array $blueprint, int $sequence, CarbonImmutable $today, array $investigators): void
    {
        $docketedOn = $today->subDays($blueprint['docketed']);

        $case = CaseModel::factory()
            ->assignedTo($investigators[$blueprint['caseload']])
            ->create([
                'docket_no' => sprintf('%s%d-%04d', self::DOCKET_PREFIX, $docketedOn->year, $sequence),
                'case_title' => $blueprint['title'],
                'incident_details' => $this->incidentDetails($blueprint['title'], $docketedOn),
                'source_info' => $blueprint['source'],
                'status' => $blueprint['status'],
                'complexity_weight' => $blueprint['weight'],
            ]);

        $timeline = CaseTimeline::factory()->docketedOn($docketedOn);

        if ($blueprint['rop'] !== null) {
            $timeline = $timeline->ropSubmitted($blueprint['rop']);
        }

        if ($blueprint['fir'] !== null) {
            $timeline = $timeline->firSubmitted($blueprint['fir'], $blueprint['recipient']);
        }

        $timeline->create(['case_id' => $case->id]);

        Complainant::factory()->count($blueprint['complainants'])->create(['case_id' => $case->id]);
        Victim::factory()->count($blueprint['victims'])->create(['case_id' => $case->id]);
        Respondent::factory()->count($blueprint['respondents'])->create(['case_id' => $case->id]);
    }

    /**
     * A short narrative so the matrix has something to read, tied to the
     * case title and the date it was docketed.
     */
    private function incidentDetails(string $title, CarbonImmutable $docketedOn): string
    {
        return sprintf(
            'PLACEHOLDER DEMO NARRATIVE. %s. The incident was reported to the regional '
            .'office on %s and docketed the same day. Statements were taken from the '
            .'parties named below. This text is sample data for demonstration only and '
            .'does not describe a real incident.',
            $title,
            $docketedOn->format('j F Y')
        );
    }

    /**
     * Print the bucket spread so the alert-logic work has a reference.
     */
    private function summarise(): void
    {
        if (! $this->command) {
            return;
        }

        $today = CarbonImmutable::today();

        $rows = CaseModel::with(['timeline', 'investigator'])
            ->where('docket_no', 'like', self::DOCKET_PREFIX.'%')
            ->orderBy('id')
            ->get()
            ->map(function (CaseModel $case) use ($today) {
                $timeline = $case->timeline;

                return [
                    self::bucketFor($timeline, $today),
                    $case->docket_no,
                    $case->investigator?->full_name ?? '—',
                    $timeline?->date_submission_rop ? 'filed' : '—',
                    $this->relativeDays($timeline?->extension_30_days, $today),
                    $this->relativeDays($timeline?->submission_60th_day, $today),
                    $this->relativeDays($timeline?->submission_120th_day, $today),
                    $timeline?->date_fir_submitted?->format('d M Y') ?? '—',
                ];
            })
            ->sortBy(fn (array $row) => array_search($row[0], ['OVERDUE', 'DUE SOON', 'ON TRACK', 'CLOSED'], true))
            ->values()
            ->all();

        $this->command->newLine();
        $this->command->table(
            ['Bucket', 'Docket', 'Investigator', 'ROP', '30th day', '60th day', '120th day', 'FIR filed'],
            $rows
        );
        $this->command->line('  Deadline columns show days from today: -n = passed, +n = upcoming.');
        $this->command->line('  The 60th day is shown but not classified — it binds torture cases only,');
        $this->command->line('  and no case-type field exists yet to identify them.');
    }

    private function relativeDays(mixed $deadline, CarbonImmutable $today): string
    {
        if (! $deadline) {
            return '—';
        }

        $days = $today->diffInDays(CarbonImmutable::parse($deadline), false);

        return sprintf('%+d d', $days);
    }

    private function say(string $message, string $level = 'info'): void
    {
        $this->command?->{$level}($message);
    }
}
