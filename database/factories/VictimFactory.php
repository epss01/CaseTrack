<?php

namespace Database\Factories;

use App\Models\CaseModel;
use Database\Factories\Support\PhilippineNames;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Victim>
 */
class VictimFactory extends Factory
{
    /**
     * How the victim came out of the incident.
     *
     * @var list<string>
     */
    public const STATUSES = [
        'Injured', 'Detained', 'Deceased', 'Missing',
        'Harassed', 'Displaced', 'Threatened', 'Released',
    ];

    /**
     * The sector the victim belongs to, as the office reports it.
     *
     * @var list<string>
     */
    public const SECTORS = [
        'Farmer', 'Fisherfolk', 'Urban poor', 'Indigenous peoples',
        'Student', 'Labor', 'Women', 'Children', 'Elderly',
        'Persons with disability', 'Church worker', 'Media',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => fn () => CaseModel::factory(),
            'name' => PhilippineNames::person(),
            // The column is nullable: age is often unknown at intake.
            'age' => fake()->optional(0.85)->numberBetween(3, 88),
            'status' => fake()->randomElement(self::STATUSES),
            'sector' => fake()->randomElement(self::SECTORS),
        ];
    }

    /**
     * A victim whose age was never established.
     */
    public function ageUnknown(): static
    {
        return $this->state(fn (array $attributes) => ['age' => null]);
    }
}
