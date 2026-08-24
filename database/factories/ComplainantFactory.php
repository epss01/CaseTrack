<?php

namespace Database\Factories;

use App\Models\CaseModel;
use Database\Factories\Support\PhilippineNames;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Complainant>
 */
class ComplainantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => fn () => CaseModel::factory(),
            'name' => PhilippineNames::person(),
        ];
    }
}
