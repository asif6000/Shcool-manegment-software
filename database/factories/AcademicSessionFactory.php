<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    protected $model = AcademicSession::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2020, 2030);

        return [
            'name' => (string) $year,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'status' => 'upcoming',
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn (): array => [
            'status' => 'active',
            'is_current' => true,
        ]);
    }
}
