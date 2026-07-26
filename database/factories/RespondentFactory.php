<?php

namespace Database\Factories;

use App\Models\CaseModel;
use Database\Factories\Support\PhilippineNames;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Respondent>
 */
class RespondentFactory extends Factory
{
    /**
     * Agencies and sectors whose respondents are not.
     *
     * @var list<string>
     */
    public const CIVILIAN_SECTORS = [
        'Barangay official', 'LGU', 'Private individual', 'Private security',
    ];

    /**
     * Standing of a respondent who is a serving officer.
     *
     * @var list<string>
     */
    public const UNIFORMED_STATUSES = [
        'Active duty', 'Suspended', 'Reassigned', 'Relieved', 'On leave', 'Retired',
    ];

    /**
     * Standing of a respondent who is not.
     *
     * @var list<string>
     */
    public const CIVILIAN_STATUSES = [
        'Under investigation', 'Summoned', 'Responded to notice', 'No response',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Rank, agency and standing are picked together: a barangay captain
        // should not come out filed under the PNP.
        return array_merge(
            fake()->boolean(70) ? $this->uniformed() : $this->civilian(),
            [
                'case_id' => fn () => CaseModel::factory(),
                // Both columns are nullable, unlike a victim's: a respondent
                // is often only a name when the case is docketed.
                'age' => fake()->optional(0.6)->numberBetween(21, 64),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function uniformed(): array
    {
        // Pick the service first, then a rank that service actually uses.
        $agency = fake()->randomElement(PhilippineNames::uniformedAgencies());

        return [
            'name' => PhilippineNames::officerOf($agency),
            'status' => fake()->optional(0.75)->randomElement(self::UNIFORMED_STATUSES),
            // Sometimes the service is simply not recorded; the rank still
            // matches whichever one it was.
            'sector' => fake()->boolean(85) ? $agency : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function civilian(): array
    {
        return [
            'name' => PhilippineNames::civilianRespondent(),
            'status' => fake()->optional(0.75)->randomElement(self::CIVILIAN_STATUSES),
            'sector' => fake()->optional(0.85)->randomElement(self::CIVILIAN_SECTORS),
        ];
    }

    /**
     * A respondent recorded as a name and nothing else.
     */
    public function unidentified(): static
    {
        return $this->state(fn (array $attributes) => [
            'age' => null,
            'status' => null,
            'sector' => null,
        ]);
    }
}
