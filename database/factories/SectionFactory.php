<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'name' => fake()->randomElement(['A', 'B', 'C']),
            'capacity' => fake()->numberBetween(30, 60),
            'is_active' => true,
        ];
    }
}
