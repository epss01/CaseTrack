<?php

namespace Database\Factories;

use App\Models\CaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CaseTimeline>
 */
class CaseTimelineFactory extends Factory
{
    /**
     * Days after the date of docket that each monitoring deadline falls on.
     *
     * The 30/60/120-day columns are deadlines, not records of when a
     * submission happened — date_submission_rop and date_fir_submitted are
     * the actuals. Keeping the offsets here means the seeder and any future
     * alert logic derive them from one place.
     */
    public const EXTENSION_DAYS = 30;

    public const SIXTIETH_DAY = 60;

    public const HUNDRED_TWENTIETH_DAY = 120;

    /**
     * The office's own target for the FIR, set ahead of the 120-day deadline
     * so a case can be behind target while still inside the deadline.
     */
    public const FIR_TARGET_DAYS = 100;

    /**
     * Who a finished FIR gets submitted to.
     *
     * @var list<string>
     */
    public const RECIPIENTS = [
        'Regional Director',
        'Commission en Banc',
        'Central Office - Legal Division',
        'Office of the Regional Prosecutor',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $docketedOn = CarbonImmutable::parse(fake()->dateTimeBetween('-150 days', '-5 days'))->startOfDay();

        return array_merge(self::deadlinesFor($docketedOn), [
            'case_id' => fn () => CaseModel::factory(),
            'date_submission_rop' => null,
            'date_fir_submitted' => null,
            'date_submitted_to' => null,
        ]);
    }

    /**
     * The date of docket plus every deadline that hangs off it.
     *
     * Public so DemoDataSeeder — and later the alert logic — can apply the
     * same rule without restating the offsets.
     *
     * @return array<string, \Carbon\CarbonImmutable>
     */
    public static function deadlinesFor(CarbonImmutable $docketedOn): array
    {
        return [
            'date_of_docket' => $docketedOn,
            'extension_30_days' => $docketedOn->addDays(self::EXTENSION_DAYS),
            'submission_60th_day' => $docketedOn->addDays(self::SIXTIETH_DAY),
            'submission_120th_day' => $docketedOn->addDays(self::HUNDRED_TWENTIETH_DAY),
            'target_date_fir' => $docketedOn->addDays(self::FIR_TARGET_DAYS),
        ];
    }

    /**
     * Docket the case on a given date and recompute every deadline from it.
     */
    public function docketedOn(CarbonImmutable|string $date): static
    {
        $docketedOn = CarbonImmutable::parse($date)->startOfDay();

        return $this->state(fn (array $attributes) => self::deadlinesFor($docketedOn));
    }

    /**
     * Record the ROP as actually submitted, this many days after docketing.
     */
    public function ropSubmitted(int $daysAfterDocket): static
    {
        return $this->state(fn (array $attributes) => [
            'date_submission_rop' => CarbonImmutable::parse($attributes['date_of_docket'])
                ->addDays($daysAfterDocket),
        ]);
    }

    /**
     * Record the FIR as filed, this many days after docketing.
     */
    public function firSubmitted(int $daysAfterDocket, ?string $recipient = null): static
    {
        return $this->state(fn (array $attributes) => [
            'date_fir_submitted' => CarbonImmutable::parse($attributes['date_of_docket'])
                ->addDays($daysAfterDocket),
            'date_submitted_to' => $recipient ?? fake()->randomElement(self::RECIPIENTS),
        ]);
    }
}
