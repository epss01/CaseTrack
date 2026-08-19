<?php

namespace Database\Factories;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CaseModel>
 */
class CaseModelFactory extends Factory
{
    protected $model = CaseModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'docket_no' => 'CHR-VIII-'.fake()->unique()->numerify('####-####'),
            'case_title' => fake()->sentence(4),
            'incident_details' => fake()->paragraph(),
            'source_info' => fake()->randomElement(['Walk-in', 'Referral', 'Media report']),
            'investigator_id' => fn () => User::factory()->investigator(),
            'status' => fake()->randomElement([
                CaseModel::STATUS_DOCKETED,
                CaseModel::STATUS_UNDER_INVESTIGATION,
                CaseModel::STATUS_FOR_REVIEW,
                CaseModel::STATUS_CLOSED,
            ]),
            'complexity_weight' => fake()->numberBetween(1, 5),
        ];
    }

    /**
     * Assign the case to a specific investigator.
     */
    public function assignedTo(User $investigator): static
    {
        return $this->state(fn (array $attributes) => [
            'investigator_id' => $investigator->id,
        ]);
    }
}
