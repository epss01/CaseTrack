<?php

namespace Database\Factories;

use App\Models\CaseModel;
use App\Services\CaseDeadlineService;
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
     * the actuals.
     *
     * These now live on CaseDeadlineService, which owns every date calculation
     * in the application; a factory is a development artifact and the alert
     * logic must not read its constants. Kept here as aliases so existing
     * references resolve to the same one source.
     */
    public const EXTENSION_DAYS = CaseDeadlineService::EXTENSION_DAYS;

    public const SIXTIETH_DAY = CaseDeadlineService::SIXTIETH_DAY;

    public const HUNDRED_TWENTIETH_DAY = CaseDeadlineService::HUNDRED_TWENTIETH_DAY;

    /**
     * The office's own target for the FIR, set ahead of the 120-day deadline
     * so a case can be behind target while still inside the deadline.
     */
    public const FIR_TARGET_DAYS = CaseDeadlineService::FIR_TARGET_DAYS;

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
     * Delegates to CaseDeadlineService, which is where the offsets and the
     * arithmetic live. Kept as a method here because the seeder and its tests
     * call it, and because a factory reading the service is the right way round.
     *
     * @return array<string, \Carbon\CarbonImmutable>
     */
    public static function deadlinesFor(CarbonImmutable $docketedOn): array
    {
        return CaseDeadlineService::deadlinesFor($docketedOn);
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
