<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        $level = fake()->numberBetween(1, 10);

        return [
            'name' => 'Class '.$level,
            'level' => $level,
            'is_active' => true,
        ];
    }
}
