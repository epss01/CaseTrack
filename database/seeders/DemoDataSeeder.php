<?php

namespace Database\Seeders;

use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\Complainant;
use App\Models\Respondent;
use App\Models\Role;
use App\Models\User;
use App\Models\Victim;
use App\Services\CaseDeadlineService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
     * Supervisors hold no caseload, so they keep the default rating.
     *
     * @var list<array{username: string, first: string, last: string}>
     */
    private const SUPERVISORS = [
        ['username' => 'talonzo', 'first' => 'Teresita', 'last' => 'Alonzo'],
        ['username' => 'epadilla', 'first' => 'Ernesto', 'last' => 'Padilla'],
    ];

    /**
     * Ratings are spread so that ranking by Workload Capacity Score is
     * visibly not the same as ranking by caseload: with these values the
     * heaviest-loaded investigator is not the highest-scoring one, and the
     * (2 - P_i) factor is what makes the difference.
     *
     * @var list<array{username: string, first: string, last: string, rating: float}>
     */
    private const INVESTIGATORS = [
        ['username' => 'abautista', 'first' => 'Ana Marie', 'last' => 'Bautista', 'rating' => 1.0],
        ['username' => 'rtan', 'first' => 'Rogelio', 'last' => 'Tan', 'rating' => 0.5],
        ['username' => 'clim', 'first' => 'Cristina', 'last' => 'Lim', 'rating' => 0.9],
        ['username' => 'focampo', 'first' => 'Ferdinand', 'last' => 'Ocampo', 'rating' => 0.4],
        ['username' => 'mserrano', 'first' => 'Maribel', 'last' => 'Serrano', 'rating' => 0.7],
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
            'source' => 'Walk-in', 'status' => CaseModel::STATUS_UNDER_INVESTIGATION, 'weight' => 4,
            'rop' => 12, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 2, 'respondents' => 1,
        ],
        [
            'bucket' => 'OVERDUE', 'caseload' => 0, 'docketed' => 165,
            'title' => 'Reported maltreatment of detainees, Ormoc City Jail',
            'source' => 'Referral', 'status' => CaseModel::STATUS_UNDER_INVESTIGATION, 'weight' => 5,
            'rop' => 20, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 3, 'respondents' => 2,
        ],
        [
            'bucket' => 'OVERDUE', 'caseload' => 1, 'docketed' => 140,
            'title' => 'Complaint on demolition without notice, Tacloban City',
            'source' => 'Walk-in', 'status' => CaseModel::STATUS_FOR_REVIEW, 'weight' => 3,
            'rop' => 9, 'fir' => null, 'recipient' => null,
            'complainants' => 2, 'victims' => 2, 'respondents' => 1,
        ],
        [
            // Nothing submitted at all: overdue on every deadline at once.
            'bucket' => 'OVERDUE', 'caseload' => 2, 'docketed' => 128,
            'title' => 'Alleged harassment of fisherfolk, Guiuan',
            'source' => 'Media report', 'status' => CaseModel::STATUS_UNDER_INVESTIGATION, 'weight' => 2,
            'rop' => null, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 1, 'respondents' => 1,
        ],

        // --------------------------------------------------------- DUE SOON
        [
            'bucket' => 'DUE SOON', 'caseload' => 0, 'docketed' => 118,
            'title' => 'Alleged excessive force during checkpoint, Palo',
            'source' => 'Referral', 'status' => CaseModel::STATUS_FOR_REVIEW, 'weight' => 3,
            'rop' => 10, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 1, 'respondents' => 2,
        ],
        [
            // No complainant: opened on the Commission's own initiative.
            'bucket' => 'DUE SOON', 'caseload' => 1, 'docketed' => 113,
            'title' => 'Reported enforced disappearance, Catbalogan',
            'source' => 'Media report', 'status' => CaseModel::STATUS_UNDER_INVESTIGATION, 'weight' => 5,
            'rop' => 15, 'fir' => null, 'recipient' => null,
            'complainants' => 0, 'victims' => 1, 'respondents' => 2,
        ],
        [
            'bucket' => 'DUE SOON', 'caseload' => 2, 'docketed' => 110,
            'title' => 'Complaint on custodial abuse, Calbayog',
            'source' => 'Walk-in', 'status' => CaseModel::STATUS_UNDER_INVESTIGATION, 'weight' => 4,
            'rop' => 12, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 2, 'respondents' => 1,
        ],
        [
            // Due soon on the ROP rather than the FIR: the 30-day mark is
            // days away and nothing has been filed against it yet.
            'bucket' => 'DUE SOON', 'caseload' => 3, 'docketed' => 24,
            'title' => 'Alleged illegal eviction of IP families, Samar',
            'source' => 'Endorsement', 'status' => CaseModel::STATUS_DOCKETED, 'weight' => 3,
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
            'source' => 'Referral', 'status' => CaseModel::STATUS_UNDER_INVESTIGATION, 'weight' => 2,
            'rop' => 14, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 2, 'respondents' => 1,
        ],
        [
            'bucket' => 'ON TRACK', 'caseload' => 1, 'docketed' => 14,
            'title' => 'Complaint on denial of medical attention in detention, Baybay',
            'source' => 'Walk-in', 'status' => CaseModel::STATUS_UNDER_INVESTIGATION, 'weight' => 3,
            'rop' => 9, 'fir' => null, 'recipient' => null,
            'complainants' => 1, 'victims' => 1, 'respondents' => 1,
        ],
        [
            'bucket' => 'ON TRACK', 'caseload' => 2, 'docketed' => 6,
            'title' => 'Alleged verbal abuse by barangay officials, Basey',
            'source' => 'Walk-in', 'status' => CaseModel::STATUS_DOCKETED, 'weight' => 1,
            'rop' => null, 'fir' => null, 'recipient' => null,
            'complainants' => 0, 'victims' => 1, 'respondents' => 1,
        ],
        [
            'bucket' => 'ON TRACK', 'caseload' => 3, 'docketed' => 2,
            'title' => 'Reported labour rights violation, Isabel',
            'source' => 'Referral', 'status' => CaseModel::STATUS_DOCKETED, 'weight' => 2,
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
            'source' => 'Referral', 'status' => CaseModel::STATUS_CLOSED, 'weight' => 5,
            'rop' => 11, 'fir' => 95, 'recipient' => 'Regional Director',
            'complainants' => 1, 'victims' => 1, 'respondents' => 2,
        ],
        [
            // Filed after the office's own FIR target but inside the 120-day
            // deadline: behind target, not late.
            'bucket' => 'CLOSED', 'caseload' => 1, 'docketed' => 132,
            'title' => 'Complaint on unlawful search of residence, Maasin',
            'source' => 'Walk-in', 'status' => CaseModel::STATUS_CLOSED, 'weight' => 3,
            'rop' => 7, 'fir' => 118, 'recipient' => 'Commission en Banc',
            'complainants' => 1, 'victims' => 2, 'respondents' => 1,
        ],
        [
            'bucket' => 'CLOSED', 'caseload' => 4, 'docketed' => 95,
            'title' => 'Reported child rights violation, Naval',
            'source' => 'Endorsement', 'status' => CaseModel::STATUS_CLOSED, 'weight' => 2,
            'rop' => 6, 'fir' => 88, 'recipient' => 'Regional Director',
            'complainants' => 2, 'victims' => 1, 'respondents' => 1,
        ],
    ];

    /**
     * Classify a timeline into one of the four demo buckets.
     *
     * This was a placeholder for the real alert logic; that logic now exists,
     * so this is a label for it rather than a second copy of it. The rule it
     * used to state — a deadline binds only while its submission is
     * outstanding, and the 60th day is excluded for want of a case-type field —
     * moved wholesale into CaseDeadlineService and is documented there.
     *
     * Keeping the method means the seeder, its summary table and its tests
     * still agree on what "overdue" means, and that DemoDataSeederTest doubles
     * as a check that the service agrees with the seeded spread.
     */
    public static function bucketFor(CaseTimeline $timeline, ?CarbonImmutable $today = null): string
    {
        $status = (new CaseDeadlineService)->caseStatusFor($timeline, $today);

        return match ($status) {
            CaseDeadlineService::STATUS_OVERDUE => 'OVERDUE',
            CaseDeadlineService::STATUS_DUE_SOON => 'DUE SOON',
            CaseDeadlineService::STATUS_ON_TRACK => 'ON TRACK',
            // STATUS_SUBMITTED, or null for a timeline with no date of docket —
            // neither of which the seeder can produce, both of which mean there
            // is no outstanding deadline to report.
            default => 'CLOSED',
        };
    }

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException(
                'Seeding demo accounts is refused outside local/testing (APP_ENV='.app()->environment().').'
            );
        }

        $this->call(RoleSeeder::class);

        $credentials = [];
        $supervisors = $this->staff(self::SUPERVISORS, Role::SUPERVISOR, $credentials);
        $investigators = $this->staff(self::INVESTIGATORS, Role::INVESTIGATOR, $credentials);

        $this->say('Demo staff ready: '.count($supervisors).' supervisors, '
            .count($investigators).' investigators.');

        if ($credentials) {
            $this->command?->table(['username', 'password'], $credentials);
            $this->command?->warn('Copy these now — they are not stored or shown again.');
        }

        $this->say('Existing accounts keep their current password — reset a lost one with '
            .'"php artisan users:rotate-password <username> --actor=<you>".');

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
     * @param  list<array{username: string, first: string, last: string, rating?: float}>  $people
     * @param  list<array{0: string, 1: string}>  $credentials  Accumulates [username, password] for accounts created this call.
     * @return list<User>
     */
    private function staff(array $people, string $roleName, array &$credentials): array
    {
        return array_map(function (array $person) use ($roleName, &$credentials) {
            $existing = User::where('username', $person['username'])->first();

            if ($existing) {
                // Re-apply the rating: an account seeded before ratings
                // existed would otherwise keep the column default and the
                // documented WCS spread would not show up.
                $existing->update(['performance_rating' => $person['rating'] ?? 1.0]);

                return $existing;
            }

            $password = Str::password(16);
            $credentials[] = [$person['username'], $password];

            return User::factory()->withRole($roleName)->create([
                'username' => $person['username'],
                'first_name' => $person['first'],
                'last_name' => $person['last'],
                'is_staff' => $roleName === Role::SUPERVISOR,
                'performance_rating' => $person['rating'] ?? 1.0,
                'password' => $password,
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

        $this->summariseWorkload();
    }

    /**
     * Print the Workload Capacity Score for each investigator.
     *
     * Ordered by score, which is deliberately not the same order as either
     * case count or complexity sum — that difference is the (2 - P_i) factor
     * doing its job.
     */
    private function summariseWorkload(): void
    {
        $rows = User::query()
            ->whereRelation('role', 'role_name', Role::INVESTIGATOR)
            ->withCount(['cases as active_cases_count' => fn ($query) => $query->active()])
            ->withSum(['cases as active_complexity_sum' => fn ($query) => $query->active()], 'complexity_weight')
            ->get()
            ->sortBy(fn (User $investigator) => $investigator->workloadCapacityScore())
            ->map(fn (User $investigator) => [
                $investigator->full_name,
                $investigator->active_cases_count,
                $investigator->active_complexity_sum ?? 0,
                number_format($investigator->performance_rating, 1),
                number_format($investigator->workloadCapacityScore(), 1),
            ])
            ->values()
            ->all();

        $this->command->newLine();
        $this->command->table(
            ['Investigator', 'Active cases', 'Complexity (sum C)', 'Rating (P)', 'WCS'],
            $rows
        );
        $this->command->line('  WCS = sum(C_j) x (2 - P_i) over active cases. Lowest is suggested next.');
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
